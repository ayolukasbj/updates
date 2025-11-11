<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'News Categories Management';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Create categories table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS news_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            slug VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            color VARCHAR(7) DEFAULT '#667eea',
            icon VARCHAR(50) DEFAULT 'fas fa-folder',
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    // Table might already exist
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $name = trim($_POST['name'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $color = trim($_POST['color'] ?? '#667eea');
                $icon = trim($_POST['icon'] ?? 'fas fa-folder');
                $sort_order = (int)($_POST['sort_order'] ?? 0);
                
                if (empty($name)) {
                    $error = 'Category name is required';
                } else {
                    // Generate slug if not provided
                    if (empty($slug)) {
                        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO news_categories (name, slug, description, color, icon, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $slug, $description, $color, $icon, $sort_order]);
                    $success = 'Category added successfully!';
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $color = trim($_POST['color'] ?? '#667eea');
                $icon = trim($_POST['icon'] ?? 'fas fa-folder');
                $sort_order = (int)($_POST['sort_order'] ?? 0);
                
                if (empty($name)) {
                    $error = 'Category name is required';
                } else {
                    // Generate slug if not provided
                    if (empty($slug)) {
                        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
                    }
                    
                    $stmt = $conn->prepare("UPDATE news_categories SET name = ?, slug = ?, description = ?, color = ?, icon = ?, sort_order = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $description, $color, $icon, $sort_order, $id]);
                    $success = 'Category updated successfully!';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $conn->prepare("DELETE FROM news_categories WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Category deleted successfully!';
                break;
                
            case 'toggle':
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $conn->prepare("UPDATE news_categories SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Category status updated!';
                break;
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = 'Category name or slug already exists';
        } else {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get all categories
$stmt = $conn->query("SELECT * FROM news_categories ORDER BY sort_order ASC, name ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get category usage count
foreach ($categories as &$category) {
    $usageStmt = $conn->prepare("SELECT COUNT(*) as count FROM news WHERE category = ?");
    $usageStmt->execute([$category['name']]);
    $usage = $usageStmt->fetch(PDO::FETCH_ASSOC);
    $category['usage_count'] = (int)($usage['count'] ?? 0);
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-tags"></i> News Categories Management</h1>
    <p>Manage categories for news/blog articles</p>
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

<!-- Add/Edit Form -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2 id="form-title">Add New Category</h2>
    </div>
    <div class="card-body">
        <form method="POST" id="category-form">
            <input type="hidden" name="action" id="form-action" value="add">
            <input type="hidden" name="id" id="form-id" value="">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" name="name" id="form-name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Slug (URL-friendly)</label>
                    <input type="text" name="slug" id="form-slug" class="form-control" placeholder="Auto-generated if empty">
                </div>
                
                <div class="form-group">
                    <label>Color</label>
                    <input type="color" name="color" id="form-color" class="form-control" value="#667eea" style="height: 38px;">
                </div>
                
                <div class="form-group">
                    <label>Icon (Font Awesome class)</label>
                    <input type="text" name="icon" id="form-icon" class="form-control" value="fas fa-folder" placeholder="fas fa-folder">
                    <small style="color: #666;">Example: fas fa-folder, fas fa-music, fas fa-star</small>
                </div>
                
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" id="form-sort-order" class="form-control" value="0" min="0">
                    <small style="color: #666;">Lower numbers appear first</small>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="form-description" class="form-control" rows="3" placeholder="Optional description"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Category
                </button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Categories List -->
<div class="card">
    <div class="card-header">
        <h2>Categories (<?php echo count($categories); ?>)</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Color</th>
                        <th>Icon</th>
                        <th>Sort</th>
                        <th>Usage</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: #999; padding: 30px;">No categories found. Create one above!</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?php echo $cat['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                            <td><code><?php echo htmlspecialchars($cat['slug']); ?></code></td>
                            <td>
                                <span style="display: inline-block; width: 30px; height: 30px; background: <?php echo htmlspecialchars($cat['color']); ?>; border-radius: 4px; border: 1px solid #ddd;"></span>
                            </td>
                            <td><i class="<?php echo htmlspecialchars($cat['icon']); ?>"></i> <?php echo htmlspecialchars($cat['icon']); ?></td>
                            <td><?php echo $cat['sort_order']; ?></td>
                            <td>
                                <?php if ($cat['usage_count'] > 0): ?>
                                <span class="badge badge-info"><?php echo $cat['usage_count']; ?> articles</span>
                                <?php else: ?>
                                <span style="color: #999;">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($cat['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                <span class="badge badge-warning">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-<?php echo $cat['is_active'] ? 'warning' : 'success'; ?>" title="<?php echo $cat['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas fa-<?php echo $cat['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                    </button>
                                </form>
                                <?php if ($cat['usage_count'] == 0): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <button type="button" class="btn btn-sm btn-danger" disabled title="Cannot delete - <?php echo $cat['usage_count']; ?> article(s) using this category">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
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
function editCategory(cat) {
    document.getElementById('form-title').textContent = 'Edit Category';
    document.getElementById('form-action').value = 'edit';
    document.getElementById('form-id').value = cat.id;
    document.getElementById('form-name').value = cat.name || '';
    document.getElementById('form-slug').value = cat.slug || '';
    document.getElementById('form-description').value = cat.description || '';
    document.getElementById('form-color').value = cat.color || '#667eea';
    document.getElementById('form-icon').value = cat.icon || 'fas fa-folder';
    document.getElementById('form-sort-order').value = cat.sort_order || 0;
    
    // Scroll to form
    document.getElementById('category-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function resetForm() {
    document.getElementById('form-title').textContent = 'Add New Category';
    document.getElementById('form-action').value = 'add';
    document.getElementById('form-id').value = '';
    document.getElementById('category-form').reset();
    document.getElementById('form-color').value = '#667eea';
    document.getElementById('form-icon').value = 'fas fa-folder';
    document.getElementById('form-sort-order').value = 0;
}

// Auto-generate slug from name
document.getElementById('form-name').addEventListener('input', function() {
    if (!document.getElementById('form-id').value) { // Only auto-generate for new categories
        const slug = this.value.toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        document.getElementById('form-slug').value = slug;
    }
});
</script>

<?php include 'includes/footer.php'; ?>

