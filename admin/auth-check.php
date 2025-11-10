<?php
/**
 * Admin Authentication Check
 * Include this file at the top of all admin pages
 */

// Enable error reporting but don't display (log only)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Load config first, then database
if (file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
}
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        die("Database connection failed");
    }
    
    // Check which columns exist
    $checkStmt = $conn->query("SHOW COLUMNS FROM users");
    $existingColumns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
    $roleExists = in_array('role', $existingColumns);
    $hasIsActive = in_array('is_active', $existingColumns);
    $hasStatus = in_array('status', $existingColumns);
    
    // Build WHERE clause based on existing columns
    $whereClause = "id = ?";
    if ($hasIsActive) {
        $whereClause .= " AND is_active = 1";
    } elseif ($hasStatus) {
        $whereClause .= " AND status = 'active'";
    }
    
    if ($roleExists) {
        $selectFields = ['role', 'username', 'email'];
        $sql = "SELECT " . implode(', ', $selectFields) . " FROM users WHERE $whereClause";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
            // Not an admin, redirect to regular dashboard
            header('Location: ../dashboard.php');
            exit;
        }
        
        // Store admin info in session
        $_SESSION['admin_role'] = $user['role'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_email'] = $user['email'];
    } else {
        // If role column doesn't exist, allow access for now
        $sql = "SELECT username, email FROM users WHERE $whereClause";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['admin_role'] = 'admin';
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_email'] = $user['email'];
        }
    }
    
} catch (Exception $e) {
    error_log("Admin auth check error: " . $e->getMessage());
    die("Authentication error: " . $e->getMessage());
}

/**
 * Log admin activity
 */
function logAdminActivity($admin_id, $action, $target_type = null, $target_id = null, $description = null) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Check if admin_logs table exists
        $stmt = $conn->query("SHOW TABLES LIKE 'admin_logs'");
        if ($stmt->rowCount() == 0) {
            return; // Table doesn't exist yet
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt = $conn->prepare("
            INSERT INTO admin_logs (admin_id, action, target_type, target_id, description, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$admin_id, $action, $target_type, $target_id, $description, $ip]);
        
    } catch (Exception $e) {
        error_log("Failed to log admin activity: " . $e->getMessage());
    }
}

/**
 * Check if user is super admin
 */
function isSuperAdmin() {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin';
}
