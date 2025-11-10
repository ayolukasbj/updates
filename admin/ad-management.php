<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Ad Management';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'save_ad':
                $ad_id = (int)($_POST['ad_id'] ?? 0);
                $ad_position = trim($_POST['ad_position'] ?? '');
                $ad_type = trim($_POST['ad_type'] ?? '');
                $ad_title = trim($_POST['ad_title'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($ad_position)) {
                    $error = 'Ad position is required!';
                    break;
                }
                
                if ($ad_id > 0) {
                    // Update existing ad
                    if ($ad_type === 'code') {
                        $ad_content = trim($_POST['ad_code'] ?? '');
                        $stmt = $conn->prepare("UPDATE ads SET position = ?, type = ?, title = ?, content = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$ad_position, $ad_type, $ad_title, $ad_content, $is_active, $ad_id]);
                    } else if ($ad_type === 'image') {
                        // Handle image upload
                        $ad_content = '';
                        if (isset($_FILES['ad_image']) && $_FILES['ad_image']['error'] === UPLOAD_ERR_OK) {
                            $upload_dir = '../uploads/ads/';
                            if (!file_exists($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            $file_ext = pathinfo($_FILES['ad_image']['name'], PATHINFO_EXTENSION);
                            $filename = 'ad_' . time() . '.' . $file_ext;
                            $filepath = $upload_dir . $filename;
                            if (move_uploaded_file($_FILES['ad_image']['tmp_name'], $filepath)) {
                                // Store relative path for database
                                $ad_content = 'uploads/ads/' . $filename;
                            }
                        } else {
                            $ad_content = trim($_POST['ad_image_url'] ?? '');
                        }
                        $ad_link = trim($_POST['ad_link'] ?? '');
                        $stmt = $conn->prepare("UPDATE ads SET position = ?, type = ?, title = ?, content = ?, link = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$ad_position, $ad_type, $ad_title, $ad_content, $ad_link, $is_active, $ad_id]);
                    } else if ($ad_type === 'video') {
                        // Handle video upload or URL
                        $ad_content = '';
                        if (isset($_FILES['ad_video_file']) && $_FILES['ad_video_file']['error'] === UPLOAD_ERR_OK) {
                            $upload_dir = '../uploads/ads/videos/';
                            if (!file_exists($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            $file_ext = pathinfo($_FILES['ad_video_file']['name'], PATHINFO_EXTENSION);
                            $allowed_exts = ['mp4', 'webm', 'ogg', 'mov'];
                            if (in_array(strtolower($file_ext), $allowed_exts)) {
                                $filename = 'ad_video_' . time() . '.' . $file_ext;
                                $filepath = $upload_dir . $filename;
                                if (move_uploaded_file($_FILES['ad_video_file']['tmp_name'], $filepath)) {
                                    // Store relative path for database
                                    $ad_content = 'uploads/ads/videos/' . $filename;
                                }
                            }
                        } else {
                            $ad_content = trim($_POST['ad_video_url'] ?? '');
                        }
                        $stmt = $conn->prepare("UPDATE ads SET position = ?, type = ?, title = ?, content = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$ad_position, $ad_type, $ad_title, $ad_content, $is_active, $ad_id]);
                    }
                    $success = 'Ad updated successfully!';
                } else {
                    // Create new ad
                    if ($ad_type === 'code') {
                        $ad_content = trim($_POST['ad_code'] ?? '');
                        $stmt = $conn->prepare("INSERT INTO ads (position, type, title, content, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$ad_position, $ad_type, $ad_title, $ad_content, $is_active]);
                    } else if ($ad_type === 'image') {
                        $ad_content = '';
                        if (isset($_FILES['ad_image']) && $_FILES['ad_image']['error'] === UPLOAD_ERR_OK) {
                            $upload_dir = '../uploads/ads/';
                            if (!file_exists($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            $file_ext = pathinfo($_FILES['ad_image']['name'], PATHINFO_EXTENSION);
                            $filename = 'ad_' . time() . '.' . $file_ext;
                            $filepath = $upload_dir . $filename;
                            if (move_uploaded_file($_FILES['ad_image']['tmp_name'], $filepath)) {
                                // Store relative path for database
                                $ad_content = 'uploads/ads/' . $filename;
                            }
                        } else {
                            $ad_content = trim($_POST['ad_image_url'] ?? '');
                        }
                        $ad_link = trim($_POST['ad_link'] ?? '');
                        $stmt = $conn->prepare("INSERT INTO ads (position, type, title, content, link, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$ad_position, $ad_type, $ad_title, $ad_content, $ad_link, $is_active]);
                    } else if ($ad_type === 'video') {
                        // Handle video upload or URL
                        $ad_content = '';
                        if (isset($_FILES['ad_video_file']) && $_FILES['ad_video_file']['error'] === UPLOAD_ERR_OK) {
                            $upload_dir = '../uploads/ads/videos/';
                            if (!file_exists($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            $file_ext = pathinfo($_FILES['ad_video_file']['name'], PATHINFO_EXTENSION);
                            $allowed_exts = ['mp4', 'webm', 'ogg', 'mov'];
                            if (in_array(strtolower($file_ext), $allowed_exts)) {
                                $filename = 'ad_video_' . time() . '.' . $file_ext;
                                $filepath = $upload_dir . $filename;
                                if (move_uploaded_file($_FILES['ad_video_file']['tmp_name'], $filepath)) {
                                    // Store relative path for database
                                    $ad_content = 'uploads/ads/videos/' . $filename;
                                }
                            }
                        } else {
                            $ad_content = trim($_POST['ad_video_url'] ?? '');
                        }
                        $stmt = $conn->prepare("INSERT INTO ads (position, type, title, content, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$ad_position, $ad_type, $ad_title, $ad_content, $is_active]);
                    }
                    $success = 'Ad created successfully!';
                }
                break;
                
            case 'delete_ad':
                $ad_id = (int)($_POST['ad_id'] ?? 0);
                if ($ad_id > 0) {
                    $stmt = $conn->prepare("DELETE FROM ads WHERE id = ?");
                    $stmt->execute([$ad_id]);
                    $success = 'Ad deleted successfully!';
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get ad positions
$ad_positions = [
    'header' => 'Header (Top of page)',
    'sidebar' => 'Sidebar',
    'content_top' => 'Content Top',
    'content_mid' => 'Content Middle',
    'content_bottom' => 'Content Bottom',
    'content_paragraph' => 'Content Between Paragraphs (News Details)',
    'news_sidebar_345' => 'News Details Sidebar (345x345)',
    'footer' => 'Footer',
    'homepage_banner' => 'Homepage Banner',
    'below_politics' => 'Below Politics Section',
    'below_business' => 'Below Business Section',
    'below_tech' => 'Below Tech Section',
    'below_popular_stories' => 'Below Popular Stories Section',
    'below_music_chart' => 'Below Music Chart Section',
    'below_newly_added' => 'Below Newly Added Songs Section',
    'song_details_top' => 'Song Details Top',
    'song_details_bottom' => 'Song Details Bottom',
    'artist_profile_top' => 'Artist Profile Top',
    'artist_profile_bottom' => 'Artist Profile Bottom',
];

// Get all ads
try {
    $stmt = $conn->query("SELECT * FROM ads ORDER BY position ASC, created_at DESC");
    $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Create ads table if doesn't exist
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS ads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                position VARCHAR(50) NOT NULL,
                type ENUM('code', 'image', 'video') NOT NULL,
                title VARCHAR(255),
                content TEXT,
                link VARCHAR(500),
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_position (position),
                INDEX idx_active (is_active)
            )
        ");
        $ads = [];
    } catch (Exception $e2) {
        $ads = [];
        $error = 'Database error: ' . $e2->getMessage();
    }
}

// Get ad to edit
$edit_ad = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($ads as $ad) {
        if ($ad['id'] == $edit_id) {
            $edit_ad = $ad;
            break;
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Ad Management</h1>
    <p>Manage advertisements on your website - Add code, images, or video ads</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Add/Edit Ad Form -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2><?php echo $edit_ad ? 'Edit Ad' : 'Add New Ad'; ?></h2>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_ad">
            <?php if ($edit_ad): ?>
            <input type="hidden" name="ad_id" value="<?php echo $edit_ad['id']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Ad Position *</label>
                <select name="ad_position" class="form-control" required>
                    <option value="">Select Position</option>
                    <?php foreach ($ad_positions as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo ($edit_ad && $edit_ad['position'] === $key) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Ad Type *</label>
                <select name="ad_type" id="ad_type" class="form-control" required onchange="toggleAdFields()">
                    <option value="">Select Type</option>
                    <option value="code" <?php echo ($edit_ad && $edit_ad['type'] === 'code') ? 'selected' : ''; ?>>Code/HTML</option>
                    <option value="image" <?php echo ($edit_ad && $edit_ad['type'] === 'image') ? 'selected' : ''; ?>>Image</option>
                    <option value="video" <?php echo ($edit_ad && $edit_ad['type'] === 'video') ? 'selected' : ''; ?>>Video</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Ad Title</label>
                <input type="text" name="ad_title" class="form-control" value="<?php echo htmlspecialchars($edit_ad['title'] ?? ''); ?>" placeholder="Optional title for identification">
            </div>
            
            <!-- Code Ad Fields -->
            <div id="code_fields" style="display: none;">
                <div class="form-group">
                    <label>Ad Code/HTML *</label>
                    <textarea name="ad_code" class="form-control" rows="10" placeholder="Paste your ad code here (Google Adsense, etc.)"><?php echo htmlspecialchars($edit_ad && $edit_ad['type'] === 'code' ? $edit_ad['content'] : ''); ?></textarea>
                    <small>Paste your advertising code (e.g., Google AdSense, custom HTML)</small>
                </div>
            </div>
            
            <!-- Image Ad Fields -->
            <div id="image_fields" style="display: none;">
                <div class="form-group">
                    <label>Upload Image</label>
                    <input type="file" name="ad_image" class="form-control" accept="image/*">
                    <small>Or use image URL below</small>
                </div>
                <div class="form-group">
                    <label>Image URL</label>
                    <input type="url" name="ad_image_url" class="form-control" value="<?php echo htmlspecialchars($edit_ad && $edit_ad['type'] === 'image' ? $edit_ad['content'] : ''); ?>" placeholder="https://example.com/ad-image.jpg">
                </div>
                <div class="form-group">
                    <label>Link URL</label>
                    <input type="url" name="ad_link" class="form-control" value="<?php echo htmlspecialchars($edit_ad['link'] ?? ''); ?>" placeholder="https://example.com">
                </div>
            </div>
            
            <!-- Video Ad Fields -->
            <div id="video_fields" style="display: none;">
                <div class="form-group">
                    <label>Upload Video File</label>
                    <input type="file" name="ad_video_file" class="form-control" accept="video/*">
                    <small>Supported formats: MP4, WebM, OGG, MOV</small>
                </div>
                <div class="form-group">
                    <label>OR Video URL</label>
                    <input type="url" name="ad_video_url" class="form-control" value="<?php echo htmlspecialchars($edit_ad && $edit_ad['type'] === 'video' && (strpos($edit_ad['content'], 'http') === 0 || strpos($edit_ad['content'], 'uploads/ads/videos/') === false) ? $edit_ad['content'] : ''); ?>" placeholder="https://example.com/video.mp4 or YouTube/Vimeo URL">
                    <small>Supported: Direct video URL, YouTube URL, Vimeo URL</small>
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" <?php echo ($edit_ad && $edit_ad['is_active']) ? 'checked' : 'checked'; ?>>
                    Active (Show on website)
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary"><?php echo $edit_ad ? 'Update Ad' : 'Create Ad'; ?></button>
            <?php if ($edit_ad): ?>
            <a href="ad-management.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Ads List -->
<div class="card">
    <div class="card-header">
        <h2>All Ads (<?php echo count($ads); ?>)</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Position</th>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ads)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #999;">No ads yet</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($ads as $ad): ?>
                        <tr>
                            <td><?php echo $ad['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($ad_positions[$ad['position']] ?? $ad['position']); ?></strong></td>
                            <td><span class="badge badge-info"><?php echo ucfirst($ad['type']); ?></span></td>
                            <td><?php echo htmlspecialchars($ad['title'] ?? '-'); ?></td>
                            <td>
                                <span class="badge <?php echo $ad['is_active'] ? 'badge-success' : 'badge-warning'; ?>">
                                    <?php echo $ad['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($ad['created_at'])); ?></td>
                            <td>
                                <a href="?edit=<?php echo $ad['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this ad?');">
                                    <input type="hidden" name="action" value="delete_ad">
                                    <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleAdFields() {
    var adType = document.getElementById('ad_type').value;
    
    // Hide all fields
    document.getElementById('code_fields').style.display = 'none';
    document.getElementById('image_fields').style.display = 'none';
    document.getElementById('video_fields').style.display = 'none';
    
    // Show relevant fields
    if (adType === 'code') {
        document.getElementById('code_fields').style.display = 'block';
    } else if (adType === 'image') {
        document.getElementById('image_fields').style.display = 'block';
    } else if (adType === 'video') {
        document.getElementById('video_fields').style.display = 'block';
    }
}

// Initialize on page load
<?php if ($edit_ad): ?>
toggleAdFields();
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>

