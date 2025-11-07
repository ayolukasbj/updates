<?php
require_once 'config/config.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Helper function to get setting
function getSetting($key, $default = '') {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

$privacy_content = getSetting('privacy_policy', '');
$site_name = defined('SITE_NAME') ? SITE_NAME : 'Music Platform';

if (empty($privacy_content)) {
    $privacy_content = '<h1>Privacy Policy</h1>
<p><strong>Last Updated:</strong> ' . date('F j, Y') . '</p>

<h2>1. Information We Collect</h2>
<p>We collect information that you provide directly to us, such as when you:</p>
<ul>
    <li>Create an account</li>
    <li>Upload content</li>
    <li>Contact us for support</li>
    <li>Participate in interactive features</li>
</ul>

<h2>2. How We Use Your Information</h2>
<p>We use the information we collect to:</p>
<ul>
    <li>Provide, maintain, and improve our services</li>
    <li>Process transactions and send related information</li>
    <li>Send technical notices and support messages</li>
    <li>Respond to your comments and questions</li>
    <li>Monitor and analyze trends and usage</li>
</ul>

<h2>3. Information Sharing</h2>
<p>We do not sell, trade, or otherwise transfer your personal information to third parties without your consent, except as described in this policy.</p>

<h2>4. Data Security</h2>
<p>We implement appropriate technical and organizational measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>

<h2>5. Cookies</h2>
<p>We use cookies to enhance your experience, analyze site usage, and assist in our marketing efforts.</p>

<h2>6. Your Rights</h2>
<p>You have the right to:</p>
<ul>
    <li>Access your personal information</li>
    <li>Correct inaccurate data</li>
    <li>Request deletion of your data</li>
    <li>Object to processing of your data</li>
</ul>

<h2>7. Children\'s Privacy</h2>
<p>Our Service is not intended for children under the age of 13. We do not knowingly collect personal information from children under 13.</p>

<h2>8. Changes to This Policy</h2>
<p>We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page.</p>

<h2>9. Contact Us</h2>
<p>If you have any questions about this Privacy Policy, please contact us.</p>';
}

$page_title = 'Privacy Policy - ' . $site_name;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/luo-style.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
            background: #fff;
            margin-top: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .content {
            line-height: 1.8;
        }
        .content h1 {
            font-size: 36px;
            margin-bottom: 20px;
            color: #222;
        }
        .content h2 {
            font-size: 24px;
            margin-top: 30px;
            margin-bottom: 15px;
            color: #222;
        }
        .content p {
            margin-bottom: 15px;
        }
        .content ul {
            margin-left: 30px;
            margin-bottom: 15px;
        }
        .content li {
            margin-bottom: 8px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <a href="javascript:history.back()" class="back-link">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <div class="content">
            <?php echo $privacy_content; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>

