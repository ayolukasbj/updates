<?php
/**
 * Admin Account Verification Tool
 * Use this to check if your admin account exists and verify credentials
 */

require_once '../config/config.php';
require_once '../config/database.php';

$message = '';
$user_info = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email)) {
        $message = 'Please enter your email address';
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            if (!$conn) {
                throw new Exception('Database connection failed');
            }
            
            // Check which columns exist
            $checkStmt = $conn->query("SHOW COLUMNS FROM users");
            $existingColumns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Find user by email (case-insensitive)
            $selectFields = ['id', 'username', 'email', 'password'];
            if (in_array('role', $existingColumns)) {
                $selectFields[] = 'role';
            }
            if (in_array('status', $existingColumns)) {
                $selectFields[] = 'status';
            }
            if (in_array('is_active', $existingColumns)) {
                $selectFields[] = 'is_active';
            }
            
            $sql = "SELECT " . implode(', ', $selectFields) . " FROM users WHERE LOWER(email) = LOWER(?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$email]);
            $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user_info) {
                $message = '❌ No user found with email: ' . htmlspecialchars($email);
            } else {
                $message = '✅ User found!';
                
                // Test password if provided
                if (!empty($password)) {
                    if (empty($user_info['password'])) {
                        $message .= '<br>❌ Password is not set in database';
                    } elseif (password_verify($password, $user_info['password'])) {
                        $message .= '<br>✅ Password is correct!';
                    } else {
                        $message .= '<br>❌ Password is incorrect';
                    }
                }
            }
            
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Admin Account</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .verify-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); width: 100%; max-width: 600px; }
        .verify-header { text-align: center; margin-bottom: 30px; }
        .verify-header i { font-size: 60px; color: #667eea; margin-bottom: 15px; }
        .verify-header h1 { font-size: 28px; color: #333; margin-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 5px; font-size: 14px; }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; font-size: 14px; }
        .message.info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
        .message.error { background: #fee; color: #c33; border-left: 4px solid #c33; }
        .message.success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .btn-verify { width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn-verify:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4); }
        .user-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; }
        .user-info h3 { margin-bottom: 10px; color: #333; }
        .user-info p { margin: 5px 0; color: #666; }
        .user-info code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #667eea; text-decoration: none; }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-header">
            <i class="fas fa-user-check"></i>
            <h1>Verify Admin Account</h1>
            <p>Check if your admin account exists and verify credentials</p>
        </div>

        <?php if ($message): ?>
        <div class="message <?php 
            echo strpos($message, '✅') !== false ? 'success' : 
                (strpos($message, '❌') !== false ? 'error' : 'info'); 
        ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <?php if ($user_info): ?>
        <div class="user-info">
            <h3>Account Information:</h3>
            <p><strong>ID:</strong> <code><?php echo $user_info['id']; ?></code></p>
            <p><strong>Username:</strong> <code><?php echo htmlspecialchars($user_info['username'] ?? 'N/A'); ?></code></p>
            <p><strong>Email:</strong> <code><?php echo htmlspecialchars($user_info['email']); ?></code></p>
            <?php if (isset($user_info['role'])): ?>
            <p><strong>Role:</strong> <code><?php echo htmlspecialchars($user_info['role']); ?></code></p>
            <?php endif; ?>
            <?php if (isset($user_info['status'])): ?>
            <p><strong>Status:</strong> <code><?php echo htmlspecialchars($user_info['status']); ?></code></p>
            <?php endif; ?>
            <?php if (isset($user_info['is_active'])): ?>
            <p><strong>Is Active:</strong> <code><?php echo $user_info['is_active'] ? 'Yes' : 'No'; ?></code></p>
            <?php endif; ?>
            <p><strong>Password Hash:</strong> <code><?php echo !empty($user_info['password']) ? substr($user_info['password'], 0, 20) . '...' : 'NOT SET'; ?></code></p>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       placeholder="Enter your admin email">
            </div>

            <div class="form-group">
                <label>Password (Optional - to verify password)</label>
                <input type="password" name="password" 
                       placeholder="Enter password to verify">
            </div>

            <button type="submit" class="btn-verify">
                <i class="fas fa-search"></i> Verify Account
            </button>
        </form>

        <div class="back-link">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</body>
</html>

