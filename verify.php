<?php
// license-server/api/verify.php
// License Verification API Endpoint
// This file should be placed at: https://hylinktech.com/api/verify.php

require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['valid' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get request data
$license_key = trim($_POST['license_key'] ?? $_GET['license_key'] ?? '');
$domain = trim($_POST['domain'] ?? $_GET['domain'] ?? '');
$ip = trim($_POST['ip'] ?? $_GET['ip'] ?? '');

if (empty($license_key)) {
    http_response_code(400);
    echo json_encode(['valid' => false, 'message' => 'License key is required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Get license
    $stmt = $conn->prepare("SELECT * FROM licenses WHERE license_key = ?");
    $stmt->execute([$license_key]);
    $license = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$license) {
        // Log failed verification
        $logStmt = $conn->prepare("
            INSERT INTO license_logs (license_key, action, domain, ip_address, status, message)
            VALUES (?, 'verify', ?, ?, 'failed', 'License key not found')
        ");
        $logStmt->execute([$license_key, $domain, $ip]);
        
        http_response_code(404);
        echo json_encode(['valid' => false, 'message' => 'License key not found']);
        exit;
    }
    
    // Check status
    if ($license['status'] !== 'active') {
        $logStmt = $conn->prepare("
            INSERT INTO license_logs (license_id, license_key, action, domain, ip_address, status, message)
            VALUES (?, ?, 'verify', ?, ?, 'failed', 'License is not active')
        ");
        $logStmt->execute([$license['id'], $license_key, $domain, $ip]);
        
        http_response_code(403);
        echo json_encode(['valid' => false, 'message' => 'License is not active']);
        exit;
    }
    
    // Lifetime licenses - no expiration check needed
    // All licenses are lifetime, purchased once, no expiration
    
    // Check domain binding
    if (!empty($license['bound_domain']) && $license['bound_domain'] !== $domain) {
        // Log the domain mismatch attempt
        $logStmt = $conn->prepare("
            INSERT INTO license_logs (license_id, license_key, action, domain, ip_address, status, message)
            VALUES (?, ?, 'verify', ?, ?, 'failed', ?)
        ");
        $message = 'License is bound to different domain. Attempted domain: ' . $domain . ', Bound domain: ' . $license['bound_domain'];
        $logStmt->execute([$license['id'], $license_key, $domain, $ip, $message]);
        
        // Create/update domain_mismatch_logs table to track attempts
        try {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS domain_mismatch_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    license_id INT NOT NULL,
                    license_key VARCHAR(255) NOT NULL,
                    attempted_domain VARCHAR(255) NOT NULL,
                    bound_domain VARCHAR(255) NOT NULL,
                    ip_address VARCHAR(45),
                    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_license_id (license_id),
                    INDEX idx_license_key (license_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $mismatchStmt = $conn->prepare("
                INSERT INTO domain_mismatch_logs (license_id, license_key, attempted_domain, bound_domain, ip_address)
                VALUES (?, ?, ?, ?, ?)
            ");
            $mismatchStmt->execute([$license['id'], $license_key, $domain, $license['bound_domain'], $ip]);
        } catch (Exception $e) {
            // Log error but continue
            error_log("Error creating domain mismatch log: " . $e->getMessage());
        }
        
        http_response_code(403);
        echo json_encode([
            'valid' => false, 
            'message' => 'This license is already being used on another domain (' . $license['bound_domain'] . '). Please purchase a license to use this script. Contact us at +256773814006 / 0777122100 or email us at info@hylinktech.com',
            'bound_domain' => $license['bound_domain'],
            'attempted_domain' => $domain
        ]);
        exit;
    }
    
    // If domain not bound yet, bind it (first activation)
    if (empty($license['bound_domain']) && !empty($domain)) {
        $bindStmt = $conn->prepare("UPDATE licenses SET bound_domain = ?, bound_ip = ?, activated_at = NOW(), status = 'active' WHERE id = ?");
        $bindStmt->execute([$domain, $ip, $license['id']]);
        $license['bound_domain'] = $domain;
        $license['bound_ip'] = $ip;
    }
    
    // Update verification count and last verified
    $updateStmt = $conn->prepare("
        UPDATE licenses 
        SET last_verified = NOW(), verification_count = verification_count + 1 
        WHERE id = ?
    ");
    $updateStmt->execute([$license['id']]);
    
    // Log successful verification
    $logStmt = $conn->prepare("
        INSERT INTO license_logs (license_id, license_key, action, domain, ip_address, status, message)
        VALUES (?, ?, 'verify', ?, ?, 'success', 'License verified successfully')
    ");
    $logStmt->execute([$license['id'], $license_key, $domain, $ip]);
    
    // Return success
    http_response_code(200);
    echo json_encode([
        'valid' => true,
        'message' => 'License is valid',
        'license' => [
            'type' => $license['license_type'],
            'expires_at' => $license['expires_at'],
            'bound_domain' => $license['bound_domain']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("License verification error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'valid' => false, 
        'message' => 'License verification error'
    ]);
}

