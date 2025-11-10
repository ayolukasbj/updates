<?php
// Enable error reporting for debugging (but catch fatal errors)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session with error handling
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in as admin, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    try {
        require_once '../config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        // Check if role column exists
        $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
        $roleExists = $stmt->rowCount() > 0;
        
        if ($roleExists) {
            $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && in_array($user['role'], ['admin', 'super_admin'])) {
                header('Location: index.php');
                exit;
            }
        }
    } catch (Exception $e) {
        // Ignore and continue to login form
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Load config first, then database
    if (file_exists('../config/config.php')) {
        require_once '../config/config.php';
    }
    require_once '../config/database.php';
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            if (!$conn) {
                $error = 'Database connection failed';
            } else {
                // Check which columns exist
                try {
                    $checkStmt = $conn->query("SHOW COLUMNS FROM users");
                    $existingColumns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                } catch (Exception $e) {
                    // Fallback if SHOW COLUMNS fails
                    $existingColumns = ['id', 'username', 'email', 'password', 'role', 'status'];
                    error_log("Could not check columns: " . $e->getMessage());
                }
                
                $roleExists = in_array('role', $existingColumns);
                $hasIsActive = in_array('is_active', $existingColumns);
                $hasStatus = in_array('status', $existingColumns);
                
                // Build SELECT query based on existing columns
                $selectFields = ['id', 'username', 'email', 'password'];
                if ($roleExists) {
                    $selectFields[] = 'role';
                }
                if ($hasIsActive) {
                    $selectFields[] = 'is_active';
                }
                if ($hasStatus) {
                    $selectFields[] = 'status';
                }
                
                // Try email match (case-insensitive)
                $sql = "SELECT " . implode(', ', $selectFields) . " FROM users WHERE LOWER(email) = LOWER(?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([trim($email)]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    $error = 'No user found with this email address. Please check your email or <a href="setup-admin.php" style="color: #fff;">setup admin access</a>.';
                } elseif (empty($user['password'])) {
                    $error = 'Password not set for this account. Please contact administrator or <a href="reset-password.php" style="color: #fff;">reset password</a>.';
                } elseif (!password_verify($password, $user['password'])) {
                    $error = 'Invalid password. Please check your password and try again. <a href="reset-password.php" style="color: #fff;">Forgot password?</a>';
                } else {
                    // Check if account is active/deactivated
                    $isDeactivated = false;
                    if ($hasIsActive && isset($user['is_active']) && !$user['is_active']) {
                        $isDeactivated = true;
                    } elseif ($hasStatus && isset($user['status']) && $user['status'] !== 'active') {
                        $isDeactivated = true;
                    }
                    
                    if ($isDeactivated) {
                        $error = 'Your account has been deactivated';
                    } elseif ($roleExists && !in_array($user['role'] ?? '', ['admin', 'super_admin'])) {
                        $error = 'Access denied. Admin privileges required. <br><a href="setup-admin.php" style="color: #fff;">Click here to upgrade your account</a>';
                    } else {
                        // Success - login the user
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['admin_role'] = $user['role'] ?? 'admin';
                        
                        // Update last login
                        try {
                            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                            $updateStmt->execute([$user['id']]);
                        } catch (Exception $e) {
                            // Ignore if last_login column doesn't exist
                            error_log("Could not update last_login: " . $e->getMessage());
                        }
                        
                        header('Location: index.php');
                        exit;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Admin login error: " . $e->getMessage());
            $error = 'An error occurred: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Music Platform</title>
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

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 420px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header i {
            font-size: 60px;
            color: #667eea;
            margin-bottom: 15px;
        }

        .login-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 5px;
        }

        .login-header p {
            color: #666;
            font-size: 14px;
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

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c33;
        }

        .error-message a {
            color: #c33;
            font-weight: bold;
        }

        .btn-login {
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

        .btn-login:hover {
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
            font-size: 14px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .setup-link {
            text-align: center;
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .setup-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-shield-alt"></i>
            <h1>Admin Panel</h1>
            <p>Sign in to access the admin dashboard</p>
        </div>

        <?php if ($error): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" required placeholder="admin@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <div class="setup-link">
            <i class="fas fa-info-circle"></i> First time? 
            <a href="setup-admin.php">Setup Admin Access</a> | 
            <a href="reset-password.php">Reset Password</a>
        </div>

        <div class="back-link">
            <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Website</a>
        </div>
    </div>
</body>
</html>
