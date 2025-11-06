<?php
/**
 * Admin Password Reset Tool
 * Use this if you forgot your admin password or need to reset it
 */

require_once '../config/config.php';
require_once '../config/database.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($email)) {
        $message = 'Please enter your email address';
    } elseif (empty($new_password)) {
        $message = 'Please enter a new password';
    } elseif (strlen($new_password) < 8) {
        $message = 'Password must be at least 8 characters';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Passwords do not match';
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
            $roleExists = in_array('role', $existingColumns);
            
            // Find user by email (case-insensitive)
            $stmt = $conn->prepare("SELECT id, username, email" . ($roleExists ? ", role" : "") . " FROM users WHERE LOWER(email) = LOWER(?)");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $message = 'No user found with this email address';
            } else {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password
                $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$hashed_password, $user['id']]);
                
                $success = true;
                $message = "Password reset successfully!<br><br>You can now login with:<br><strong>Email:</strong> {$user['email']}<br><strong>Password:</strong> (the new password you just set)<br><br><a href='login.php' style='color: #fff; text-decoration: underline;'>Go to Login</a>";
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
    <title>Reset Admin Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .reset-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 500px;
        }

        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .reset-header i {
            font-size: 60px;
            color: #667eea;
            margin-bottom: 15px;
        }

        .reset-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .message.error {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .btn-reset {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <i class="fas fa-key"></i>
            <h1>Reset Admin Password</h1>
        </div>

        <?php if ($message): ?>
        <div class="message <?php echo $success ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       placeholder="Enter your admin email">
            </div>

            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required 
                       minlength="8"
                       placeholder="Enter new password (min 8 characters)">
            </div>

            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required 
                       minlength="8"
                       placeholder="Confirm new password">
            </div>

            <button type="submit" class="btn-reset">
                <i class="fas fa-key"></i> Reset Password
            </button>
        </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</body>
</html>

