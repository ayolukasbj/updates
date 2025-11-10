<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Send Newsletter';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Get newsletter subscribers
$subscribers = [];
try {
    // Check if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'newsletter_subscribers'");
    if ($checkTable->rowCount() > 0) {
        $stmt = $conn->query("SELECT * FROM newsletter_subscribers WHERE status = 'active' ORDER BY subscribed_at DESC");
        $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $subscribers = [];
    // Don't show error if table doesn't exist yet
}

// Get email templates
$templates = [];
try {
    $stmt = $conn->query("SELECT * FROM email_templates WHERE is_active = 1 ORDER BY name");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Templates table might not exist yet
}

// Handle newsletter sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_newsletter'])) {
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $template_id = (int)($_POST['template_id'] ?? 0);
    $send_to = $_POST['send_to'] ?? 'all';
    $test_email = trim($_POST['test_email'] ?? '');
    
    if (isset($_POST['send_test'])) {
        // Send test email
        if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid test email address';
        } else {
            require_once '../helpers/EmailHelper.php';
            
            // Replace variables in test email
            $test_subject = str_replace(['{name}', '{email}', '{site_name}', '{site_url}'], 
                ['Test User', $test_email, SITE_NAME, SITE_URL], $subject);
            $test_body = str_replace(['{name}', '{email}', '{site_name}', '{site_url}'], 
                ['Test User', $test_email, SITE_NAME, SITE_URL], $body);
            
            $sent = EmailHelper::sendEmail($test_email, $test_subject, $test_body);
            if ($sent) {
                $success = 'Test email sent successfully to ' . htmlspecialchars($test_email) . '!';
            } else {
                $error = 'Failed to send test email. Please check your email settings.';
            }
        }
    } else {
        // Send newsletter
        if (empty($subject) || empty($body)) {
            $error = 'Subject and body are required!';
        } else {
            require_once '../helpers/EmailHelper.php';
            
            // Determine recipients
            $recipients = [];
            if ($send_to === 'all') {
                $recipients = $subscribers; // Already contains email addresses
            } elseif ($send_to === 'test') {
                if (!empty($test_email) && filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
                    $recipients = [['email' => $test_email]];
                } else {
                    $error = 'Please enter a valid test email address';
                }
            }
            
            if (empty($error) && !empty($recipients)) {
                $sent_count = 0;
                $failed_count = 0;
                
                foreach ($recipients as $subscriber) {
                    $email = $subscriber['email'];
                    // Use email as name or extract name from email
                    $name = 'Subscriber';
                    if (!empty($email)) {
                        $email_parts = explode('@', $email);
                        $name = ucfirst($email_parts[0]); // Use email username part as name
                    }
                    
                    // Replace variables
                    $email_subject = str_replace(['{name}', '{email}', '{site_name}', '{site_url}'], 
                        [$name, $email, SITE_NAME, SITE_URL], $subject);
                    $email_body = str_replace(['{name}', '{email}', '{site_name}', '{site_url}'], 
                        [$name, $email, SITE_NAME, SITE_URL], $body);
                    
                    $sent = EmailHelper::sendEmail($email, $email_subject, $email_body);
                    if ($sent) {
                        $sent_count++;
                    } else {
                        $failed_count++;
                    }
                    
                    // Add delay to avoid rate limiting
                    if (count($recipients) > 1) {
                        usleep(500000); // 0.5 second delay
                    }
                }
                
                if ($send_to === 'all') {
                    $success = "Newsletter sent! Successfully sent to $sent_count subscribers";
                    if ($failed_count > 0) {
                        $success .= ", $failed_count failed";
                    }
                    logAdminActivity($_SESSION['user_id'], 'send_newsletter', 'newsletter', 0, "Sent newsletter to $sent_count subscribers");
                } else {
                    $success = "Test email sent successfully!";
                }
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Send Newsletter</h1>
    <p>Send newsletters to your subscribers</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Newsletter Statistics</h2>
        <a href="newsletter-subscribers.php" class="btn btn-primary">
            <i class="fas fa-users"></i> View All Subscribers
        </a>
    </div>
    <div class="card-body">
        <p><strong>Total Active Subscribers:</strong> <?php echo count($subscribers); ?></p>
        <p style="margin-top: 10px;">
            <a href="newsletter-subscribers.php">Manage subscribers →</a>
        </p>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Compose Newsletter</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label>Use Template (Optional)</label>
                <select name="template_id" id="template-select" class="form-control">
                    <option value="0">-- Select Template --</option>
                    <?php foreach ($templates as $template): ?>
                    <option value="<?php echo $template['id']; ?>" 
                        data-subject="<?php echo htmlspecialchars($template['subject']); ?>"
                        data-body="<?php echo htmlspecialchars($template['body']); ?>">
                        <?php echo htmlspecialchars($template['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Email Subject <span style="color: red;">*</span></label>
                <input type="text" name="subject" id="newsletter-subject" class="form-control" required 
                    placeholder="Newsletter Subject">
                <small class="text-muted">Available variables: {name}, {email}, {site_name}, {site_url}</small>
            </div>
            
            <div class="form-group">
                <label>Email Body (HTML) <span style="color: red;">*</span></label>
                <textarea name="body" id="newsletter-body" class="form-control" rows="15" required 
                    style="font-family: monospace;" placeholder="<html><body><h1>Hello {name}!</h1><p>Your newsletter content here...</p></body></html>"></textarea>
                <small class="text-muted">Use HTML for formatting. Variables: {name}, {email}, {site_name}, {site_url}</small>
            </div>
            
            <div class="form-group">
                <label>Send To</label>
                <select name="send_to" class="form-control">
                    <option value="test">Test Email (preview)</option>
                    <option value="all">All Subscribers (<?php echo count($subscribers); ?>)</option>
                </select>
            </div>
            
            <div class="form-group" id="test-email-group">
                <label>Test Email Address</label>
                <input type="email" name="test_email" class="form-control" 
                    placeholder="your-email@example.com" 
                    value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>">
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="send_test" class="btn btn-warning">Send Test Email</button>
                <button type="submit" name="send_newsletter" class="btn btn-primary" 
                    onclick="return confirm('Are you sure you want to send this newsletter to all subscribers?');">
                    Send Newsletter
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Template selection handler
document.getElementById('template-select')?.addEventListener('change', function() {
    if (this.value > 0) {
        const option = this.options[this.selectedIndex];
        const subject = option.getAttribute('data-subject');
        const body = option.getAttribute('data-body');
        
        if (subject) {
            document.getElementById('newsletter-subject').value = subject;
        }
        if (body) {
            document.getElementById('newsletter-body').value = body;
        }
    }
});

// Show/hide test email field
document.querySelector('select[name="send_to"]')?.addEventListener('change', function() {
    const testEmailGroup = document.getElementById('test-email-group');
    if (this.value === 'test') {
        testEmailGroup.style.display = 'block';
    } else {
        testEmailGroup.style.display = 'block'; // Keep visible for test button
    }
});
</script>

<?php include 'includes/footer.php'; ?>

