<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$page_id = $_GET['id'] ?? 0;
$page = null;
$success = '';
$error = '';

// Fetch page
if ($page_id) {
    $stmt = $conn->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->execute([$page_id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$page) {
        header('Location: pages.php');
        exit;
    }
    $page_title = 'Edit Page: ' . htmlspecialchars($page['title']);
} else {
    header('Location: pages.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($title) || empty($slug)) {
        $error = 'Title and slug are required';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE pages SET title = ?, slug = ?, content = ?, meta_description = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$title, $slug, $content, $meta_description, $is_active, $page_id]);
            $success = 'Page updated successfully';
            // Refresh page data
            $stmt = $conn->prepare("SELECT * FROM pages WHERE id = ?");
            $stmt->execute([$page_id]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = 'Error updating page: ' . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Edit Page</h1>
    <p>Update page content</p>
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

<form method="POST">
    <div class="card">
        <div class="card-header">
            <h2>Page Details</h2>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label>Page Title *</label>
                <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($page['title']); ?>">
            </div>
            <div class="form-group">
                <label>URL Slug *</label>
                <input type="text" name="slug" class="form-control" required value="<?php echo htmlspecialchars($page['slug']); ?>">
                <small>Used in URL: /page/<?php echo htmlspecialchars($page['slug']); ?></small>
            </div>
            <div class="form-group">
                <label>Page Content</label>
                <textarea name="content" class="form-control" rows="15" placeholder="Enter page content (HTML allowed)"><?php echo htmlspecialchars($page['content'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label>Meta Description</label>
                <input type="text" name="meta_description" class="form-control" value="<?php echo htmlspecialchars($page['meta_description'] ?? ''); ?>" placeholder="SEO meta description">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" <?php echo $page['is_active'] ? 'checked' : ''; ?>> Active
                </label>
            </div>
        </div>
    </div>
    
    <div style="margin-top: 20px;">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Changes
        </button>
        <a href="pages.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<?php include 'includes/footer.php'; ?>

