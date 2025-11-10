<?php
/**
 * Admin Login as User
 * Allows admins to log into user accounts without password
 */

require_once 'auth-check.php';
require_once '../config/database.php';

// Only allow admins
if (!isset($_SESSION['admin_role']) || !in_array($_SESSION['admin_role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    die('Access denied. Admin privileges required.');
}

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    header('Location: users.php?error=User ID required');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Verify user exists and is not a super admin
$stmt = $conn->prepare("SELECT id, username, email, role, is_banned, is_active FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$target_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target_user) {
    header('Location: users.php?error=User not found');
    exit;
}

// Prevent logging into super admin accounts
if ($target_user['role'] === 'super_admin') {
    header('Location: users.php?error=Cannot login as super admin');
    exit;
}

// Store admin session info before switching
$_SESSION['admin_original_user_id'] = $_SESSION['user_id'];
$_SESSION['admin_original_role'] = $_SESSION['admin_role'];
$_SESSION['admin_original_username'] = $_SESSION['admin_username'];
$_SESSION['admin_impersonating'] = true;
$_SESSION['admin_impersonated_user_id'] = $user_id;

// Switch to user session
$_SESSION['user_id'] = $target_user['id'];
$_SESSION['role'] = $target_user['role'];
$_SESSION['username'] = $target_user['username'];
$_SESSION['email'] = $target_user['email'];

// Log the activity
logAdminActivity(
    $_SESSION['admin_original_user_id'],
    'login_as_user',
    'user',
    $user_id,
    "Admin logged into user account: {$target_user['username']} (ID: $user_id)"
);

// Redirect to user dashboard
header('Location: ../dashboard.php');
exit;




