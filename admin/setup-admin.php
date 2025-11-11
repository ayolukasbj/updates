<?php
/**
 * Admin Role Setup Script
 * Run this once to upgrade your existing admin account to have admin privileges
 */

require_once '../config/database.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = 'Please enter your email address';
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            // Check if user exists
            $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $message = 'No user found with this email address';
            } else {
                // Check which columns exist before updating
                $checkStmt = $conn->query("SHOW COLUMNS FROM users");
                $existingColumns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Build UPDATE query based on existing columns
                $updates = ["role = 'super_admin'"];
                
                // Handle status column (created by install script) or is_active column (from schema)
                if (in_array('status', $existingColumns)) {
                    $updates[] = "status = 'active'";
                } elseif (in_array('is_active', $existingColumns)) {
                    $updates[] = "is_active = 1";
                }
                
                // Handle is_banned column if it exists
                if (in_array('is_banned', $existingColumns)) {
                    $updates[] = "is_banned = 0";
                }
                
                // Handle email_verified column if it exists
                if (in_array('email_verified', $existingColumns)) {
                    $updates[] = "email_verified = 1";
                }
                
                // Update user to super_admin role
                $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE email = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$email]);
                
                $success = true;
                $message = "Success! User '{$user['username']}' has been upgraded to Super Admin.<br><br>You can now login at: <a href='login.php' style='color: #fff; text-decoration: underline;'>Admin Login</a>";
            }
            
        } catch (Exception $e) {
            $message = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Admin Access</title>
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

        .setup-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 500px;
        }

        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .setup-header i {
            font-size: 60px;
            color: #667eea;
            margin-bottom: 15px;
        }

        .setup-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }

        .setup-header p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
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

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
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

        .btn-setup {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-setup:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .info-box h3 {
            color: #667eea;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .info-box p {
            font-size: 13px;
            color: #666;
            line-height: 1.5;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .setup-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <i class="fas fa-user-shield"></i>
            <h1>Setup Admin Access</h1>
            <p>Enter your email address to upgrade your account to Super Admin</p>
        </div>

        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> Instructions</h3>
            <p>Enter the email address you used when you first installed the platform. This will upgrade your account to Super Admin and allow you to access the admin panel.</p>
        </div>

        <?php if ($message): ?>
        <div class="message <?php echo $success ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Your Email Address</label>
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" required 
                           placeholder="Enter the email you registered with"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>

            <button type="submit" class="btn-setup">
                <i class="fas fa-key"></i> Grant Admin Access
            </button>
        </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Website</a>
        </div>
    </div>
</body>
</html>

