<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$user_id = $_GET['id'] ?? null;
$error = '';
$success = '';

if (!$user_id) {
    header('Location: users.php');
    exit;
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: users.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $email_verified = isset($_POST['email_verified']) ? 1 : 0;
    $subscription_type = $_POST['subscription_type'] ?? 'free';
    
    if (empty($username) || empty($email)) {
        $error = 'Username and email are required';
    } else {
        // Check if email/username already exists for other users
        $stmt = $conn->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?");
        $stmt->execute([$email, $username, $user_id]);
        if ($stmt->rowCount() > 0) {
            $error = 'Email or username already exists';
        } else {
            // Update user with email_verified status
            $stmt = $conn->prepare("
                UPDATE users 
                SET username = ?, email = ?, role = ?, is_active = ?, email_verified = ?, subscription_type = ?
                WHERE id = ?
            ");
            $params = [$username, $email, $role, $is_active, $email_verified, $subscription_type, $user_id];
            
            if ($stmt->execute($params)) {
                $success = 'User updated successfully';
                
                // If email verification was just enabled, clear verification token
                if ($email_verified) {
                    $stmt = $conn->prepare("UPDATE users SET verification_token = NULL WHERE id = ?");
                    $stmt->execute([$user_id]);
                }
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Failed to update user';
            }
        }
    }
}

$page_title = 'Edit User';
include 'includes/header.php';
?>

<div class="page-header">
    <h1>Edit User</h1>
    <a href="users.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Users
    </a>
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

<div class="card">
    <div class="card-header">
        <h2>User Information</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            
            <?php if (isSuperAdmin()): ?>
            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control">
                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="artist" <?php echo $user['role'] === 'artist' ? 'selected' : ''; ?>>Artist</option>
                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <?php if ($user['role'] === 'super_admin'): ?>
                    <option value="super_admin" selected>Super Admin</option>
                    <?php endif; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Subscription Type</label>
                <select name="subscription_type" class="form-control">
                    <option value="free" <?php echo $user['subscription_type'] === 'free' ? 'selected' : ''; ?>>Free</option>
                    <option value="premium" <?php echo $user['subscription_type'] === 'premium' ? 'selected' : ''; ?>>Premium</option>
                </select>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="is_active" value="1" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                    <span>Account Active</span>
                </label>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="email_verified" value="1" <?php echo ($user['email_verified'] ?? 0) ? 'checked' : ''; ?>>
                    <span>Email Verified</span>
                </label>
                <small style="color: #666; display: block; margin-top: 5px; margin-left: 28px;">
                    <i class="fas fa-info-circle"></i> Check this to manually verify the user's email if they didn't receive the verification email
                </small>
            </div>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                <h3 style="margin-bottom: 15px;">Account Stats</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <strong>User ID:</strong> <?php echo $user['id']; ?>
                    </div>
                    <div>
                        <strong>Joined:</strong> <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                    </div>
                    <div>
                        <strong>Last Login:</strong> <?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
                    </div>
                    <div>
                        <strong>Current Email Verification Status:</strong> 
                        <?php if (($user['email_verified'] ?? 0)): ?>
                            <span style="color: #10b981; font-weight: 600;">
                                <i class="fas fa-check-circle"></i> Verified
                            </span>
                        <?php else: ?>
                            <span style="color: #ef4444; font-weight: 600;">
                                <i class="fas fa-times-circle"></i> Not Verified
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="users.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

