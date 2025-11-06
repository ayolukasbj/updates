<?php
/**
 * Admin System Installer
 * Run this ONCE to add admin features to your existing database
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

$message = '';
$errors = [];
$success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        if (!$conn) {
            $errors[] = 'Database connection failed';
        } else {
            // Step 1: Add role column to users table
            try {
                $conn->exec("ALTER TABLE users ADD COLUMN role ENUM('user', 'artist', 'admin', 'super_admin') DEFAULT 'user' AFTER subscription_type");
                $success[] = 'Added role column to users table';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    $success[] = 'Role column already exists';
                } else {
                    throw $e;
                }
            }
            
            // Step 2: Add is_banned column
            try {
                $conn->exec("ALTER TABLE users ADD COLUMN is_banned BOOLEAN DEFAULT FALSE AFTER is_active");
                $success[] = 'Added is_banned column to users table';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    $success[] = 'is_banned column already exists';
                } else {
                    throw $e;
                }
            }
            
            // Step 3: Add banned_reason column
            try {
                $conn->exec("ALTER TABLE users ADD COLUMN banned_reason TEXT AFTER is_banned");
                $success[] = 'Added banned_reason column to users table';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    $success[] = 'banned_reason column already exists';
                } else {
                    throw $e;
                }
            }
            
            // Step 4: Create admin_logs table
            try {
                $conn->exec("
                    CREATE TABLE IF NOT EXISTS admin_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        admin_id INT NOT NULL,
                        action VARCHAR(100) NOT NULL,
                        target_type VARCHAR(50),
                        target_id INT,
                        description TEXT,
                        ip_address VARCHAR(45),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
                        INDEX idx_admin (admin_id),
                        INDEX idx_action (action),
                        INDEX idx_created (created_at)
                    )
                ");
                $success[] = 'Created admin_logs table';
            } catch (PDOException $e) {
                $success[] = 'admin_logs table already exists';
            }
            
            // Step 5: Create news table
            try {
                $conn->exec("
                    CREATE TABLE IF NOT EXISTS news (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(255) NOT NULL,
                        slug VARCHAR(255) UNIQUE NOT NULL,
                        category VARCHAR(50),
                        image VARCHAR(255),
                        content TEXT,
                        excerpt TEXT,
                        views INT DEFAULT 0,
                        is_published BOOLEAN DEFAULT TRUE,
                        featured BOOLEAN DEFAULT FALSE,
                        author_id INT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
                        INDEX idx_category (category),
                        INDEX idx_published (is_published),
                        INDEX idx_featured (featured),
                        INDEX idx_created (created_at)
                    )
                ");
                $success[] = 'Created news table';
            } catch (PDOException $e) {
                $success[] = 'news table already exists';
            }
            
            // Step 6: Add status column to songs
            try {
                $conn->exec("ALTER TABLE songs ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved' AFTER is_explicit");
                $success[] = 'Added status column to songs table';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    $success[] = 'status column already exists in songs table';
                } else {
                    // Ignore if songs table doesn't exist
                    $success[] = 'Songs table status update skipped';
                }
            }
            
            $message = 'Installation completed successfully!';
        }
        
    } catch (Exception $e) {
        $errors[] = 'Installation error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin System Installer</title>
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

        .install-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 600px;
        }

        .install-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .install-header i {
            font-size: 60px;
            color: #667eea;
            margin-bottom: 15px;
        }

        .install-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }

        .install-header p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
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
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .success-list, .error-list {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .success-list li {
            background: #d4edda;
            color: #155724;
            padding: 10px 15px;
            margin-bottom: 8px;
            border-radius: 5px;
            border-left: 4px solid #28a745;
        }

        .error-list li {
            background: #f8d7da;
            color: #721c24;
            padding: 10px 15px;
            margin-bottom: 8px;
            border-radius: 5px;
            border-left: 4px solid #dc3545;
        }

        .btn {
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

        .btn:hover {
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

        .info-box ul {
            font-size: 13px;
            color: #666;
            line-height: 1.8;
            margin-left: 20px;
        }

        .next-steps {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            border-left: 4px solid #0066cc;
        }

        .next-steps h3 {
            color: #0066cc;
            margin-bottom: 15px;
        }

        .next-steps ol {
            margin-left: 20px;
            line-height: 2;
        }

        .next-steps a {
            color: #0066cc;
            text-decoration: none;
            font-weight: 600;
        }

        .next-steps a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .install-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <i class="fas fa-cogs"></i>
            <h1>Admin System Installer</h1>
            <p>This will add admin features to your existing database</p>
        </div>

        <?php if (!empty($success) || !empty($errors)): ?>
            <div class="message <?php echo empty($errors) ? 'success' : 'error'; ?>">
                <strong><?php echo $message ?: 'Installation completed with some issues'; ?></strong>
            </div>
            
            <?php if (!empty($success)): ?>
            <ul class="success-list">
                <?php foreach ($success as $msg): ?>
                <li><i class="fas fa-check-circle"></i> <?php echo $msg; ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
            <ul class="error-list">
                <?php foreach ($errors as $err): ?>
                <li><i class="fas fa-exclamation-circle"></i> <?php echo $err; ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            
            <div class="next-steps">
                <h3><i class="fas fa-arrow-right"></i> Next Steps:</h3>
                <ol>
                    <li><a href="setup-admin.php">Upgrade your account to Admin</a></li>
                    <li><a href="login.php">Login to Admin Panel</a></li>
                    <li>Start managing your platform!</li>
                </ol>
            </div>
        <?php else: ?>
            <div class="info-box">
                <h3><i class="fas fa-info-circle"></i> What This Will Do:</h3>
                <ul>
                    <li>Add <code>role</code> column to users table</li>
                    <li>Add <code>is_banned</code> and <code>banned_reason</code> columns</li>
                    <li>Create <code>admin_logs</code> table for activity tracking</li>
                    <li>Create <code>news</code> table for content management</li>
                    <li>Add <code>status</code> column to songs table (if exists)</li>
                </ul>
            </div>

            <form method="POST">
                <button type="submit" class="btn">
                    <i class="fas fa-download"></i> Install Admin System
                </button>
            </form>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 20px;">
            <a href="debug.php" style="color: #667eea; text-decoration: none; font-size: 14px;">
                <i class="fas fa-bug"></i> Debug Database Status
            </a>
        </div>
    </div>
</body>
</html>

