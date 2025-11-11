<?php
/**
 * Stop Impersonating User
 * Allows admin to switch back to their admin account
 */

session_start();

// Check if admin is impersonating
if (!isset($_SESSION['admin_impersonating']) || !$_SESSION['admin_impersonating']) {
    // Not impersonating, redirect to regular dashboard
    header('Location: ../dashboard.php');
    exit;
}

// Restore admin session
$_SESSION['user_id'] = $_SESSION['admin_original_user_id'];
$_SESSION['admin_role'] = $_SESSION['admin_original_role'];
$_SESSION['admin_username'] = $_SESSION['admin_original_username'];
$_SESSION['role'] = $_SESSION['admin_original_role'];

// Clear impersonation data
unset($_SESSION['admin_impersonating']);
unset($_SESSION['admin_original_user_id']);
unset($_SESSION['admin_original_role']);
unset($_SESSION['admin_original_username']);
unset($_SESSION['admin_impersonated_user_id']);

// Redirect back to admin dashboard
header('Location: index.php');
exit;




