<?php
$page_title = 'Edit Artist';
require_once 'auth-check.php';
require_once '../config/database.php';
require_once '../classes/Artist.php';

$db = new Database();
$conn = $db->getConnection();
$artist = new Artist($conn);

$artist_id = $_GET['id'] ?? 0;
$artist_data = $artist->getArtistById($artist_id);

if (!$artist_data) {
    header('Location: artists.php?error=Artist not found');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $verified = isset($_POST['verified']) ? 1 : 0;
    
    // Handle avatar upload
    $avatar = $artist_data['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/avatars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_avatar.' . $file_ext;
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
            if ($avatar && file_exists('../' . $avatar)) {
                unlink('../' . $avatar);
            }
            $avatar = str_replace('../', '', $upload_path);
        }
    }
    
    // Handle cover image upload
    $cover_image = $artist_data['cover_image'];
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/covers/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_cover.' . $file_ext;
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_path)) {
            if ($cover_image && file_exists('../' . $cover_image)) {
                unlink('../' . $cover_image);
            }
            $cover_image = str_replace('../', '', $upload_path);
        }
    }
    
    // Handle social links
    $social_links = [
        'facebook' => $_POST['facebook'] ?? '',
        'twitter' => $_POST['twitter'] ?? '',
        'instagram' => $_POST['instagram'] ?? '',
        'youtube' => $_POST['youtube'] ?? '',
        'spotify' => $_POST['spotify'] ?? '',
        'website' => $_POST['website'] ?? ''
    ];
    
    $data = [
        'name' => $name,
        'bio' => $bio,
        'avatar' => $avatar,
        'cover_image' => $cover_image,
        'social_links' => $social_links
    ];
    
    if ($artist->updateArtist($artist_id, $data)) {
        // Update verification status separately
        if ($verified && !$artist_data['verified']) {
            $artist->verifyArtist($artist_id);
        } elseif (!$verified && $artist_data['verified']) {
            $stmt = $conn->prepare("UPDATE artists SET verified = 0 WHERE id = :id");
            $stmt->bindParam(':id', $artist_id);
            $stmt->execute();
        }
        
        $success = "Artist updated successfully!";
        $artist_data = $artist->getArtistById($artist_id);
    } else {
        $error = "Failed to update artist.";
    }
}

// Decode social links
$social_links = $artist_data['social_links'] ?? [];
if (is_string($social_links)) {
    $social_links = json_decode($social_links, true) ?: [];
}

include 'includes/header.php';
?>

<style>
    .image-preview {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #e0e0e0;
        margin-bottom: 15px;
    }
    .cover-preview {
        width: 100%;
        height: 200px;
        border-radius: 8px;
        object-fit: cover;
        border: 2px solid #e0e0e0;
        margin-bottom: 15px;
    }
    .upload-btn {
        cursor: pointer;
    }
    .social-input {
        padding-left: 45px;
    }
    .social-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 18px;
    }
    .facebook-icon { color: #1877f2; }
    .twitter-icon { color: #1da1f2; }
    .instagram-icon { color: #e4405f; }
    .youtube-icon { color: #ff0000; }
    .spotify-icon { color: #1db954; }
    .website-icon { color: #667eea; }
    .section-divider {
        border-top: 2px solid #f0f0f0;
        margin: 30px 0;
        padding-top: 20px;
    }
    .section-header {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 20px;
        color: #333;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .section-header i {
        color: #667eea;
    }
</style>

<div class="page-header">
    <h1>Edit Artist</h1>
    <p>Update artist profile information, media, and social links</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <!-- Profile Picture Section -->
            <div class="section-header">
                <i class="fas fa-user-circle"></i> Profile Picture
            </div>
            <div class="text-center mb-4">
                <img src="<?php echo !empty($artist_data['avatar']) ? '../' . htmlspecialchars($artist_data['avatar']) : '../assets/images/default-avatar.svg'; ?>" 
                     alt="Artist Avatar" class="image-preview" id="avatarPreview">
                <div>
                    <label for="avatar" class="btn btn-primary upload-btn">
                        <i class="fas fa-upload"></i> Upload Avatar
                    </label>
                    <input type="file" id="avatar" name="avatar" accept="image/*" style="display: none;" onchange="previewImage(this, 'avatarPreview')">
                </div>
            </div>

            <div class="section-divider"></div>

            <!-- Cover Image Section -->
            <div class="section-header">
                <i class="fas fa-image"></i> Cover Image
            </div>
            <div class="mb-4">
                <img src="<?php echo !empty($artist_data['cover_image']) ? '../' . htmlspecialchars($artist_data['cover_image']) : 'https://via.placeholder.com/900x200?text=Cover+Image'; ?>" 
                     alt="Cover" class="cover-preview" id="coverPreview">
                <div>
                    <label for="cover_image" class="btn btn-primary upload-btn">
                        <i class="fas fa-upload"></i> Upload Cover
                    </label>
                    <input type="file" id="cover_image" name="cover_image" accept="image/*" style="display: none;" onchange="previewImage(this, 'coverPreview')">
                </div>
            </div>

            <div class="section-divider"></div>

            <!-- Basic Information -->
            <div class="section-header">
                <i class="fas fa-info-circle"></i> Basic Information
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Artist Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($artist_data['name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-check-circle"></i> Verification Status
                    </label>
                    <div class="form-check form-switch" style="padding-left: 2.5rem; padding-top: 0.5rem;">
                        <input class="form-check-input" type="checkbox" name="verified" id="verified" value="1" 
                               <?php echo $artist_data['verified'] ? 'checked' : ''; ?> style="width: 50px; height: 25px;">
                        <label class="form-check-label" for="verified" style="margin-left: 10px;">
                            <?php echo $artist_data['verified'] ? 'Verified Artist' : 'Not Verified'; ?>
                        </label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Biography</label>
                    <textarea name="bio" class="form-control" rows="5" placeholder="Artist bio, background, achievements..."><?php echo htmlspecialchars($artist_data['bio'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="section-divider"></div>

            <!-- Social Media Links -->
            <div class="section-header">
                <i class="fas fa-share-alt"></i> Social Media Links
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Facebook</label>
                    <div class="position-relative">
                        <i class="fab fa-facebook social-icon facebook-icon"></i>
                        <input type="url" name="facebook" class="form-control social-input" placeholder="https://facebook.com/artistname" value="<?php echo htmlspecialchars($social_links['facebook'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Twitter</label>
                    <div class="position-relative">
                        <i class="fab fa-twitter social-icon twitter-icon"></i>
                        <input type="url" name="twitter" class="form-control social-input" placeholder="https://twitter.com/artistname" value="<?php echo htmlspecialchars($social_links['twitter'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Instagram</label>
                    <div class="position-relative">
                        <i class="fab fa-instagram social-icon instagram-icon"></i>
                        <input type="url" name="instagram" class="form-control social-input" placeholder="https://instagram.com/artistname" value="<?php echo htmlspecialchars($social_links['instagram'] ?? ''); ?>">
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
                        <input type="url" name="spotify" class="form-control social-input" placeholder="https://open.spotify.com/artist/..." value="<?php echo htmlspecialchars($social_links['spotify'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Website</label>
                    <div class="position-relative">
                        <i class="fas fa-globe social-icon website-icon"></i>
                        <input type="url" name="website" class="form-control social-input" placeholder="https://artistwebsite.com" value="<?php echo htmlspecialchars($social_links['website'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2 justify-content-between" style="padding-top: 20px; border-top: 2px solid #f0f0f0;">
                <div>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="artists.php" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
                <a href="artists.php?action=delete&id=<?php echo $artist_id; ?>" class="btn btn-danger btn-lg" onclick="return confirm('Are you sure you want to delete this artist? This action cannot be undone.')">
                    <i class="fas fa-trash"></i> Delete Artist
                </a>
            </div>
        </form>
    </div>
</div>

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
    
    // Update verification label when checkbox changes
    document.getElementById('verified').addEventListener('change', function() {
        const label = this.nextElementSibling;
        label.textContent = this.checked ? 'Verified Artist' : 'Not Verified';
    });
</script>

<?php include 'includes/footer.php'; ?>
