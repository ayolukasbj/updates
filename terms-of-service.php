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

$terms_content = getSetting('terms_of_service', '');
$site_name = defined('SITE_NAME') ? SITE_NAME : 'Music Platform';

if (empty($terms_content)) {
    $terms_content = '<h1>Terms of Service</h1>
<p><strong>Last Updated:</strong> ' . date('F j, Y') . '</p>

<h2>1. Acceptance of Terms</h2>
<p>By accessing and using ' . htmlspecialchars($site_name) . ', you accept and agree to be bound by the terms and provision of this agreement.</p>

<h2>2. Use License</h2>
<p>Permission is granted to temporarily use ' . htmlspecialchars($site_name) . ' for personal, non-commercial transitory viewing only.</p>

<h2>3. User Accounts</h2>
<p>When you create an account with us, you must provide information that is accurate, complete, and current at all times.</p>

<h2>4. Content</h2>
<p>Our Service allows you to post, link, store, share and otherwise make available certain information, text, graphics, videos, or other material.</p>

<h2>5. Prohibited Uses</h2>
<p>You may not use our Service:</p>
<ul>
    <li>For any unlawful purpose or to solicit others to perform unlawful acts</li>
    <li>To violate any international, federal, provincial, or state regulations, rules, laws, or local ordinances</li>
    <li>To infringe upon or violate our intellectual property rights or the intellectual property rights of others</li>
    <li>To harass, abuse, insult, harm, defame, slander, disparage, intimidate, or discriminate</li>
</ul>

<h2>6. Termination</h2>
<p>We may terminate or suspend your account and bar access to the Service immediately, without prior notice or liability, for any reason whatsoever.</p>

<h2>7. Disclaimer</h2>
<p>The information on this Service is provided on an "as is" basis. To the fullest extent permitted by law, this Company excludes all representations, warranties, conditions and terms.</p>

<h2>8. Governing Law</h2>
<p>These Terms shall be interpreted and governed by the laws of the jurisdiction in which the Company operates.</p>

<h2>9. Changes to Terms</h2>
<p>We reserve the right, at our sole discretion, to modify or replace these Terms at any time.</p>

<h2>10. Contact Us</h2>
<p>If you have any questions about these Terms, please contact us.</p>';
}

$page_title = 'Terms of Service - ' . $site_name;
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
            <?php echo $terms_content; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>


