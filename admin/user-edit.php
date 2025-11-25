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

// Check if profile columns exist
$has_bio = false;
$has_avatar = false;
$has_social_links = false;
try {
    $columns_check = $conn->query("SHOW COLUMNS FROM users");
    $columns = $columns_check->fetchAll(PDO::FETCH_COLUMN);
    $columns_lower = array_map('strtolower', $columns);
    $has_bio = in_array('bio', $columns_lower);
    $has_avatar = in_array('avatar', $columns_lower);
    $has_social_links = in_array('social_links', $columns_lower);
} catch (Exception $e) {
    error_log('Error checking users table columns: ' . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $email_verified = isset($_POST['email_verified']) ? 1 : 0;
    $subscription_type = $_POST['subscription_type'] ?? 'free';
    
    // Get bio and social links if columns exist
    $bio = $has_bio ? trim($_POST['bio'] ?? '') : '';
    
    // Handle avatar upload if column exists
    $avatar = $user['avatar'] ?? '';
    if ($has_avatar && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/avatars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_avatar.' . $file_ext;
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
            // Delete old avatar if exists
            if ($avatar && file_exists('../' . $avatar)) {
                unlink('../' . $avatar);
            }
            $avatar = 'uploads/avatars/' . $filename;
        }
    }
    
    // Handle social links if column exists
    $social_links_json = '';
    if ($has_social_links) {
        $social_links = [
            'facebook' => trim($_POST['facebook'] ?? ''),
            'twitter' => trim($_POST['twitter'] ?? ''),
            'instagram' => trim($_POST['instagram'] ?? ''),
            'youtube' => trim($_POST['youtube'] ?? ''),
            'spotify' => trim($_POST['spotify'] ?? ''),
            'website' => trim($_POST['website'] ?? '')
        ];
        $social_links_json = json_encode($social_links);
    }
    
    if (empty($username) || empty($email)) {
        $error = 'Username and email are required';
    } else {
        // Check if email/username already exists for other users
        $stmt = $conn->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?");
        $stmt->execute([$email, $username, $user_id]);
        if ($stmt->rowCount() > 0) {
            $error = 'Email or username already exists';
        } else {
            // Build UPDATE query dynamically based on available columns
            $update_fields = ['username', 'email', 'role', 'is_active', 'email_verified', 'subscription_type'];
            $update_values = [$username, $email, $role, $is_active, $email_verified, $subscription_type];
            
            if ($has_bio) {
                $update_fields[] = 'bio';
                $update_values[] = $bio;
            }
            if ($has_avatar) {
                $update_fields[] = 'avatar';
                $update_values[] = $avatar;
            }
            if ($has_social_links) {
                $update_fields[] = 'social_links';
                $update_values[] = $social_links_json;
            }
            
            $update_values[] = $user_id; // Add user_id for WHERE clause
            
            $placeholders = implode(', ', array_map(function($field) { return "$field = ?"; }, $update_fields));
            $stmt = $conn->prepare("UPDATE users SET $placeholders WHERE id = ?");
            
            if ($stmt->execute($update_values)) {
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

// Decode social links for display
$social_links = [];
if ($has_social_links && !empty($user['social_links'])) {
    if (is_string($user['social_links'])) {
        $decoded = json_decode($user['social_links'], true);
        if (is_array($decoded)) {
            $social_links = $decoded;
        }
    } elseif (is_array($user['social_links'])) {
        $social_links = $user['social_links'];
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
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            
            <?php if ($has_bio): ?>
            <div class="form-group">
                <label>Bio</label>
                <textarea name="bio" class="form-control" rows="4" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
            </div>
            <?php endif; ?>
            
            <?php if ($has_avatar): ?>
            <div class="form-group">
                <label>Profile Picture (Avatar)</label>
                <?php if (!empty($user['avatar'])): ?>
                <div style="margin-bottom: 10px;">
                    <img src="../<?php echo htmlspecialchars($user['avatar']); ?>" alt="Current Avatar" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid #e5e7eb;">
                </div>
                <?php endif; ?>
                <input type="file" name="avatar" class="form-control" accept="image/*">
                <small style="color: #666; display: block; margin-top: 5px;">
                    <i class="fas fa-info-circle"></i> Upload a new profile picture (JPG, PNG, etc.)
                </small>
            </div>
            <?php endif; ?>
            
            <?php if ($has_social_links): ?>
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                <h3 style="margin-bottom: 15px;">Social Media Links</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                    <div class="form-group">
                        <label>Facebook</label>
                        <input type="url" name="facebook" class="form-control" placeholder="https://facebook.com/username" value="<?php echo htmlspecialchars($social_links['facebook'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Twitter</label>
                        <input type="url" name="twitter" class="form-control" placeholder="https://twitter.com/username" value="<?php echo htmlspecialchars($social_links['twitter'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Instagram</label>
                        <input type="url" name="instagram" class="form-control" placeholder="https://instagram.com/username" value="<?php echo htmlspecialchars($social_links['instagram'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>YouTube</label>
                        <input type="url" name="youtube" class="form-control" placeholder="https://youtube.com/channel/..." value="<?php echo htmlspecialchars($social_links['youtube'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Spotify</label>
                        <input type="url" name="spotify" class="form-control" placeholder="https://spotify.com/artist/..." value="<?php echo htmlspecialchars($social_links['spotify'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Website</label>
                        <input type="url" name="website" class="form-control" placeholder="https://yourwebsite.com" value="<?php echo htmlspecialchars($social_links['website'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
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

