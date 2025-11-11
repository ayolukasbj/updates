<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Business Section Tabs';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Create business_tabs table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS business_tabs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tab_label VARCHAR(100) NOT NULL,
            tab_key VARCHAR(50) UNIQUE NOT NULL,
            category_filter VARCHAR(255),
            filter_type ENUM('category', 'keyword', 'custom') DEFAULT 'category',
            filter_value TEXT,
            sort_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_sort (sort_order),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Insert default tabs if table is empty
    $checkStmt = $conn->query("SELECT COUNT(*) as count FROM business_tabs");
    $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count == 0) {
        $defaultTabs = [
            ['All', 'all', 'category', 'Business', 1],
            ['News', 'news', 'category', 'Business', 2],
            ['Tech', 'tech', 'category', 'Tech', 3],
            ['Startup', 'startup', 'keyword', 'startup,entrepreneur,business', 4],
            ['World', 'world', 'category', 'World', 5]
        ];
        
        $insertStmt = $conn->prepare("
            INSERT INTO business_tabs (tab_label, tab_key, filter_type, filter_value, sort_order) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($defaultTabs as $tab) {
            $insertStmt->execute($tab);
        }
    }
} catch (Exception $e) {
    error_log("Error creating business_tabs table: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'save_tab':
                $tab_id = (int)($_POST['tab_id'] ?? 0);
                $tab_label = trim($_POST['tab_label'] ?? '');
                $tab_key = trim($_POST['tab_key'] ?? '');
                $filter_type = trim($_POST['filter_type'] ?? 'category');
                $filter_value = trim($_POST['filter_value'] ?? '');
                $sort_order = (int)($_POST['sort_order'] ?? 0);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($tab_label) || empty($tab_key)) {
                    $error = 'Tab label and key are required!';
                    break;
                }
                
                // Validate tab_key (must be alphanumeric with hyphens/underscores)
                if (!preg_match('/^[a-z0-9_-]+$/', strtolower($tab_key))) {
                    $error = 'Tab key must contain only lowercase letters, numbers, hyphens, and underscores!';
                    break;
                }
                
                if ($tab_id > 0) {
                    // Update existing tab
                    $stmt = $conn->prepare("
                        UPDATE business_tabs 
                        SET tab_label = ?, tab_key = ?, filter_type = ?, filter_value = ?, sort_order = ?, is_active = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$tab_label, strtolower($tab_key), $filter_type, $filter_value, $sort_order, $is_active, $tab_id]);
                    $success = 'Tab updated successfully!';
                } else {
                    // Create new tab
                    $stmt = $conn->prepare("
                        INSERT INTO business_tabs (tab_label, tab_key, filter_type, filter_value, sort_order, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$tab_label, strtolower($tab_key), $filter_type, $filter_value, $sort_order, $is_active]);
                    $success = 'Tab created successfully!';
                }
                
                logAdminActivity($_SESSION['user_id'], 'save_business_tab', 'business_tabs', $tab_id ?: $conn->lastInsertId(), "Saved business tab: $tab_label");
                break;
                
            case 'delete_tab':
                $tab_id = (int)($_POST['tab_id'] ?? 0);
                if ($tab_id > 0) {
                    $stmt = $conn->prepare("DELETE FROM business_tabs WHERE id = ?");
                    $stmt->execute([$tab_id]);
                    $success = 'Tab deleted successfully!';
                    logAdminActivity($_SESSION['user_id'], 'delete_business_tab', 'business_tabs', $tab_id, "Deleted business tab ID: $tab_id");
                }
                break;
                
            case 'toggle_active':
                $tab_id = (int)($_POST['tab_id'] ?? 0);
                if ($tab_id > 0) {
                    $stmt = $conn->prepare("UPDATE business_tabs SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$tab_id]);
                    $success = 'Tab status updated!';
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get all tabs
$tabs = $conn->query("
    SELECT * FROM business_tabs 
    ORDER BY sort_order ASC, tab_label ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get all news categories for dropdown (check if table exists)
$categories = [];
try {
    $checkStmt = $conn->query("SHOW TABLES LIKE 'news'");
    if ($checkStmt->rowCount() > 0) {
        $categories = $conn->query("
            SELECT DISTINCT category FROM news 
            WHERE category IS NOT NULL AND category != '' 
            ORDER BY category ASC
        ")->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {
    $categories = [];
    error_log("News table not found: " . $e->getMessage());
}

// Get current tab being edited
$edit_tab = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM business_tabs WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_tab = $stmt->fetch(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Business Section Tabs</h1>
    <p>Manage tabs displayed in the Business section on the homepage</p>
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

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2><?php echo $edit_tab ? 'Edit Tab' : 'Add New Tab'; ?></h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_tab">
            <input type="hidden" name="tab_id" value="<?php echo $edit_tab['id'] ?? 0; ?>">
            
            <div class="form-group">
                <label>Tab Label *</label>
                <input type="text" name="tab_label" class="form-control" required
                       value="<?php echo htmlspecialchars($edit_tab['tab_label'] ?? ''); ?>"
                       placeholder="e.g., All, News, Tech, Startup, World">
                <small style="color: #6b7280;">The text displayed on the tab button</small>
            </div>
            
            <div class="form-group">
                <label>Tab Key *</label>
                <input type="text" name="tab_key" class="form-control" required
                       value="<?php echo htmlspecialchars($edit_tab['tab_key'] ?? ''); ?>"
                       placeholder="e.g., all, news, tech, startup, world"
                       pattern="[a-z0-9_-]+"
                       title="Only lowercase letters, numbers, hyphens, and underscores allowed">
                <small style="color: #6b7280;">Unique identifier (lowercase, numbers, hyphens, underscores only)</small>
            </div>
            
            <div class="form-group">
                <label>Filter Type *</label>
                <select name="filter_type" class="form-control" id="filter_type" required>
                    <option value="category" <?php echo ($edit_tab['filter_type'] ?? 'category') === 'category' ? 'selected' : ''; ?>>Category</option>
                    <option value="keyword" <?php echo ($edit_tab['filter_type'] ?? '') === 'keyword' ? 'selected' : ''; ?>>Keyword Search</option>
                    <option value="custom" <?php echo ($edit_tab['filter_type'] ?? '') === 'custom' ? 'selected' : ''; ?>>Custom SQL</option>
                </select>
                <small style="color: #6b7280;">How to filter news articles for this tab</small>
            </div>
            
            <div class="form-group" id="filter_category_group">
                <label>News Category</label>
                <select name="filter_value" class="form-control" id="filter_category">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" 
                            <?php echo ($edit_tab['filter_type'] ?? '') === 'category' && ($edit_tab['filter_value'] ?? '') === $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #6b7280;">Select which news category to display</small>
            </div>
            
            <div class="form-group" id="filter_keyword_group" style="display: none;">
                <label>Keywords</label>
                <input type="text" name="filter_value" class="form-control" id="filter_keyword"
                       value="<?php echo ($edit_tab['filter_type'] ?? '') === 'keyword' ? htmlspecialchars($edit_tab['filter_value'] ?? '') : ''; ?>"
                       placeholder="e.g., startup,entrepreneur,business">
                <small style="color: #6b7280;">Comma-separated keywords to search in title and content</small>
            </div>
            
            <div class="form-group" id="filter_custom_group" style="display: none;">
                <label>Custom SQL WHERE Clause</label>
                <textarea name="filter_value" class="form-control" id="filter_custom" rows="3"
                          placeholder="e.g., category = 'Business' AND title LIKE '%startup%'"><?php echo ($edit_tab['filter_type'] ?? '') === 'custom' ? htmlspecialchars($edit_tab['filter_value'] ?? '') : ''; ?></textarea>
                <small style="color: #6b7280;">Advanced: Custom WHERE clause (use with caution, must be valid SQL)</small>
            </div>
            
            <div class="form-group">
                <label>Sort Order</label>
                <input type="number" name="sort_order" class="form-control"
                       value="<?php echo $edit_tab['sort_order'] ?? 0; ?>"
                       min="0">
                <small style="color: #6b7280;">Lower numbers appear first (0 = first tab)</small>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1"
                           <?php echo ($edit_tab['is_active'] ?? 1) ? 'checked' : ''; ?>>
                    Active
                </label>
                <small style="color: #6b7280; display: block;">Only active tabs are displayed on the homepage</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $edit_tab ? 'Update Tab' : 'Create Tab'; ?>
                </button>
                <?php if ($edit_tab): ?>
                <a href="business-tabs.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Existing Tabs</h2>
    </div>
    <div class="card-body">
        <?php if (empty($tabs)): ?>
        <p style="color: #6b7280;">No tabs created yet. Add one above to get started.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Label</th>
                        <th>Key</th>
                        <th>Filter Type</th>
                        <th>Filter Value</th>
                        <th>Sort Order</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tabs as $tab): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($tab['tab_label']); ?></strong></td>
                        <td><code><?php echo htmlspecialchars($tab['tab_key']); ?></code></td>
                        <td><?php echo htmlspecialchars(ucfirst($tab['filter_type'])); ?></td>
                        <td>
                            <small style="color: #6b7280;">
                                <?php 
                                $value = htmlspecialchars($tab['filter_value']);
                                echo strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value;
                                ?>
                            </small>
                        </td>
                        <td><?php echo $tab['sort_order']; ?></td>
                        <td>
                            <?php if ($tab['is_active']): ?>
                            <span class="badge badge-success">Active</span>
                            <?php else: ?>
                            <span class="badge badge-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 10px;">
                                <a href="?edit=<?php echo $tab['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to toggle this tab status?');">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="tab_id" value="<?php echo $tab['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-warning">
                                        <i class="fas fa-toggle-<?php echo $tab['is_active'] ? 'on' : 'off'; ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this tab? This cannot be undone!');">
                                    <input type="hidden" name="action" value="delete_tab">
                                    <input type="hidden" name="tab_id" value="<?php echo $tab['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterType = document.getElementById('filter_type');
    const categoryGroup = document.getElementById('filter_category_group');
    const keywordGroup = document.getElementById('filter_keyword_group');
    const customGroup = document.getElementById('filter_custom_group');
    const categorySelect = document.getElementById('filter_category');
    const keywordInput = document.getElementById('filter_keyword');
    const customTextarea = document.getElementById('filter_custom');
    
    function updateFilterInputs() {
        const type = filterType.value;
        
        // Hide all groups
        categoryGroup.style.display = 'none';
        keywordGroup.style.display = 'none';
        customGroup.style.display = 'none';
        
        // Clear required attributes
        categorySelect.removeAttribute('required');
        keywordInput.removeAttribute('required');
        customTextarea.removeAttribute('required');
        
        // Show relevant group and set required
        if (type === 'category') {
            categoryGroup.style.display = 'block';
            categorySelect.setAttribute('required', 'required');
        } else if (type === 'keyword') {
            keywordGroup.style.display = 'block';
            keywordInput.setAttribute('required', 'required');
        } else if (type === 'custom') {
            customGroup.style.display = 'block';
            customTextarea.setAttribute('required', 'required');
        }
    }
    
    // Update on change
    filterType.addEventListener('change', updateFilterInputs);
    
    // Initial update
    updateFilterInputs();
});
</script>

<?php include 'includes/footer.php'; ?>

