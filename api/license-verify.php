<?php
// api/license-verify.php
// License Verification API Endpoint (for remote server verification)

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/license.php';

header('Content-Type: application/json');

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
    $license_manager = new LicenseManager();
    $result = $license_manager->verifyLicense();
    
    // Return result
    http_response_code(200);
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'valid' => false, 
        'message' => 'License verification error: ' . $e->getMessage()
    ]);
}


