<?php
// biography-manage.php - Manage biography
require_once 'config/config.php';
require_once 'config/database.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = get_user_id();
$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_biography'])) {
    $biography = trim($_POST['biography'] ?? '');
    
    try {
        $updateStmt = $conn->prepare("UPDATE users SET bio = ? WHERE id = ?");
        $updateStmt->execute([$biography, $user_id]);
        
        $message = 'Biography updated successfully!';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Error updating biography: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get current biography
$current_bio = '';
$userStmt = $conn->prepare("SELECT bio FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$user_data = $userStmt->fetch(PDO::FETCH_ASSOC);
if ($user_data) {
    $current_bio = $user_data['bio'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Biography - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <?php include 'includes/header.php'; ?>
    <style>
        .bio-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
        }
        .bio-form {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
            font-family: inherit;
            min-height: 300px;
            resize: vertical;
        }
        .btn-save {
            background: #ff6600;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="bio-container">
        <h1 style="margin-bottom: 20px;"><i class="fas fa-user"></i> Manage Biography</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="bio-form">
            <form method="POST">
                <div class="form-group">
                    <label>Biography</label>
                    <textarea name="biography" placeholder="Tell your story, share your musical journey..."><?php echo htmlspecialchars($current_bio); ?></textarea>
                    <small style="color: #666;">This biography will appear on your public artist profile.</small>
                </div>
                
                <button type="submit" name="update_biography" class="btn-save">
                    <i class="fas fa-save"></i> Save Biography
                </button>
            </form>
        </div>
        
        <?php if (!empty($current_bio)): ?>
            <div class="bio-form" style="margin-top: 20px;">
                <h3 style="margin-bottom: 15px;">Preview</h3>
                <div style="font-size: 14px; color: #666; line-height: 1.8;">
                    <?php echo nl2br(htmlspecialchars($current_bio)); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="artist-profile-mobile.php?tab=biography" style="color: #666; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Artist Profile
            </a>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>

