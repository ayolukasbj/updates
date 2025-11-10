<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Page Management';

$db = new Database();
$conn = $db->getConnection();

// Check if pages table exists, if not create it
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS pages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(255) UNIQUE NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        meta_description VARCHAR(500),
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_slug (slug),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table might already exist
}

// Handle actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'edit') {
        $id = $_POST['id'] ?? 0;
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Generate slug from title if not provided
        if (empty($slug) && !empty($title)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        }
        
        if (empty($title) || empty($slug)) {
            $error = 'Title and slug are required';
        } else {
            try {
                if ($action === 'create') {
                    $stmt = $conn->prepare("INSERT INTO pages (slug, title, content, meta_description, is_active) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$slug, $title, $content, $meta_description, $is_active]);
                    $success = 'Page created successfully';
                } else {
                    $stmt = $conn->prepare("UPDATE pages SET title = ?, slug = ?, content = ?, meta_description = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$title, $slug, $content, $meta_description, $is_active, $id]);
                    $success = 'Page updated successfully';
                }
            } catch (Exception $e) {
                $error = 'Error saving page: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM pages WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success = 'Page deleted successfully';
        } else {
            $error = 'Error deleting page';
        }
    } elseif ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE pages SET is_active = NOT is_active WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success = 'Page status updated';
        }
    }
}

// Get all pages
$pages = [];
try {
    // Check if pages table exists
    $tableExists = false;
    try {
        $checkStmt = $conn->query("SHOW TABLES LIKE 'pages'");
        $tableExists = $checkStmt->rowCount() > 0;
    } catch (Exception $e) {
        // Table doesn't exist
    }
    
    if ($tableExists) {
        $stmt = $conn->query("SELECT * FROM pages ORDER BY created_at DESC");
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = 'Error loading pages: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Page Management</h1>
    <p>Create and manage custom pages</p>
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
        <h2>Create New Page</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Page Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="Enter page title">
            </div>
            <div class="form-group">
                <label>URL Slug *</label>
                <input type="text" name="slug" class="form-control" required placeholder="page-url-slug">
                <small>Used in URL: /page/page-url-slug</small>
            </div>
            <div class="form-group">
                <label>Page Content</label>
                <textarea name="content" class="form-control" rows="10" placeholder="Enter page content (HTML allowed)"></textarea>
            </div>
            <div class="form-group">
                <label>Meta Description</label>
                <input type="text" name="meta_description" class="form-control" placeholder="SEO meta description">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" checked> Active
                </label>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create Page
            </button>
        </form>
    </div>
</div>

<div class="card" style="margin-top: 30px;">
    <div class="card-header">
        <h2>Existing Pages</h2>
    </div>
    <div class="card-body">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pages)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                        No pages created yet. Create your first page above.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($pages as $page): ?>
                <tr>
                    <td><?php echo $page['id']; ?></td>
                    <td><?php echo htmlspecialchars($page['title']); ?></td>
                    <td><code>/page/<?php echo htmlspecialchars($page['slug']); ?></code></td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="id" value="<?php echo $page['id']; ?>">
                            <button type="submit" class="btn btn-sm <?php echo $page['is_active'] ? 'btn-success' : 'btn-secondary'; ?>">
                                <?php echo $page['is_active'] ? 'Active' : 'Inactive'; ?>
                            </button>
                        </form>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($page['created_at'])); ?></td>
                    <td>
                        <a href="page-edit.php?id=<?php echo $page['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this page?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $page['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

