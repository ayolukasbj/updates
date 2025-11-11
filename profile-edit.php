<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/User.php';

if (!is_logged_in()) {
    redirect(SITE_URL . '/login.php');
}

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$user_id = get_user_id();
$user_data = $user->getUserById($user_id);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $bio = $_POST['bio'] ?? '';
    
    // Handle profile picture upload
    $avatar = $user_data['avatar'] ?? '';
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/avatars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_avatar.' . $file_ext;
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
            // Delete old avatar if exists
            if ($avatar && file_exists($avatar)) {
                unlink($avatar);
            }
            $avatar = $upload_path;
        }
    }
    
    // Handle cover image upload
    $cover_image = $user_data['cover_image'] ?? '';
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/covers/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_cover.' . $file_ext;
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_path)) {
            // Delete old cover if exists
            if ($cover_image && file_exists($cover_image)) {
                unlink($cover_image);
            }
            $cover_image = $upload_path;
        }
    }
    
    // Handle social links
    $social_links = json_encode([
        'facebook' => $_POST['facebook'] ?? '',
        'twitter' => $_POST['twitter'] ?? '',
        'instagram' => $_POST['instagram'] ?? '',
        'youtube' => $_POST['youtube'] ?? '',
        'spotify' => $_POST['spotify'] ?? '',
        'website' => $_POST['website'] ?? ''
    ]);
    
    // Update profile
    $query = "UPDATE users SET 
              username = :username, 
              email = :email, 
              bio = :bio, 
              avatar = :avatar,
              cover_image = :cover_image,
              social_links = :social_links
              WHERE id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':bio', $bio);
    $stmt->bindParam(':avatar', $avatar);
    $stmt->bindParam(':cover_image', $cover_image);
    $stmt->bindParam(':social_links', $social_links);
    $stmt->bindParam(':id', $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['username'] = $username;
        $success = "Profile updated successfully!";
        $user_data = $user->getUserById($user_id);
    } else {
        $error = "Failed to update profile.";
    }
}

// Decode social links
$social_links = json_decode($user_data['social_links'] ?? '{}', true) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; padding-top: 80px; }
        .edit-container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .section-card { background: white; border-radius: 12px; padding: 30px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section-title { font-size: 20px; font-weight: 600; margin-bottom: 20px; color: #333; display: flex; align-items: center; gap: 10px; }
        .section-title i { color: #667eea; }
        .image-preview { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid #e0e0e0; margin-bottom: 15px; }
        .cover-preview { width: 100%; height: 200px; border-radius: 8px; object-fit: cover; border: 2px solid #e0e0e0; margin-bottom: 15px; }
        .upload-btn { cursor: pointer; }
        .social-input { padding-left: 45px; }
        .social-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); font-size: 18px; }
        .facebook-icon { color: #1877f2; }
        .twitter-icon { color: #1da1f2; }
        .instagram-icon { color: #e4405f; }
        .youtube-icon { color: #ff0000; }
        .spotify-icon { color: #1db954; }
        .website-icon { color: #667eea; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="edit-container">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <!-- Profile Picture -->
            <div class="section-card">
                <div class="section-title">
                    <i class="fas fa-user-circle"></i> Profile Picture
                </div>
                <div class="text-center">
                    <img src="<?php echo !empty($user_data['avatar']) ? htmlspecialchars($user_data['avatar']) : 'assets/images/default-avatar.svg'; ?>" 
                         alt="Profile" class="image-preview" id="avatarPreview">
                    <div>
                        <label for="avatar" class="btn btn-primary upload-btn">
                            <i class="fas fa-upload"></i> Upload Picture
                        </label>
                        <input type="file" id="avatar" name="avatar" accept="image/*" style="display: none;" onchange="previewImage(this, 'avatarPreview')">
                    </div>
                </div>
            </div>

            <!-- Cover Image -->
            <div class="section-card">
                <div class="section-title">
                    <i class="fas fa-image"></i> Cover Image
                </div>
                <img src="<?php echo !empty($user_data['cover_image']) ? htmlspecialchars($user_data['cover_image']) : 'https://via.placeholder.com/900x200?text=Cover+Image'; ?>" 
                     alt="Cover" class="cover-preview" id="coverPreview">
                <div>
                    <label for="cover_image" class="btn btn-primary upload-btn">
                        <i class="fas fa-upload"></i> Upload Cover
                    </label>
                    <input type="file" id="cover_image" name="cover_image" accept="image/*" style="display: none;" onchange="previewImage(this, 'coverPreview')">
                </div>
            </div>

            <!-- Basic Info -->
            <div class="section-card">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i> Basic Information
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Bio</label>
                        <textarea name="bio" class="form-control" rows="4" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user_data['bio'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Social Links -->
            <div class="section-card">
                <div class="section-title">
                    <i class="fas fa-share-alt"></i> Social Media Links
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Facebook</label>
                        <div class="position-relative">
                            <i class="fas fa-facebook social-icon facebook-icon"></i>
                            <input type="url" name="facebook" class="form-control social-input" placeholder="https://facebook.com/username" value="<?php echo htmlspecialchars($social_links['facebook'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Twitter</label>
                        <div class="position-relative">
                            <i class="fab fa-twitter social-icon twitter-icon"></i>
                            <input type="url" name="twitter" class="form-control social-input" placeholder="https://twitter.com/username" value="<?php echo htmlspecialchars($social_links['twitter'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Instagram</label>
                        <div class="position-relative">
                            <i class="fab fa-instagram social-icon instagram-icon"></i>
                            <input type="url" name="instagram" class="form-control social-input" placeholder="https://instagram.com/username" value="<?php echo htmlspecialchars($social_links['instagram'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">YouTube</label>
                        <div class="position-relative">
                            <i class="fab fa-youtube social-icon youtube-icon"></i>
                            <input type="url" name="youtube" class="form-control social-input" placeholder="https://youtube.com/channel/..." value="<?php echo htmlspecialchars($social_links['youtube'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Spotify</label>
                        <div class="position-relative">
                            <i class="fab fa-spotify social-icon spotify-icon"></i>
                            <input type="url" name="spotify" class="form-control social-input" placeholder="https://spotify.com/artist/..." value="<?php echo htmlspecialchars($social_links['spotify'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Website</label>
                        <div class="position-relative">
                            <i class="fas fa-globe social-icon website-icon"></i>
                            <input type="url" name="website" class="form-control social-input" placeholder="https://yourwebsite.com" value="<?php echo htmlspecialchars($social_links['website'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="section-card">
                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="profile.php" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(previewId).src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>

