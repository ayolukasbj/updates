<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Email Templates Management';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Create email_templates table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS email_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            subject VARCHAR(500) NOT NULL,
            body TEXT NOT NULL,
            variables TEXT,
            description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (slug),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_template':
                $name = trim($_POST['name'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $subject = trim($_POST['subject'] ?? '');
                $body = trim($_POST['body'] ?? '');
                $variables = trim($_POST['variables'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($name) || empty($slug) || empty($subject) || empty($body)) {
                    $error = 'Name, slug, subject, and body are required!';
                    break;
                }
                
                // Generate slug if not provided
                if (empty($slug)) {
                    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
                }
                
                $stmt = $conn->prepare("INSERT INTO email_templates (name, slug, subject, body, variables, description, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $subject, $body, $variables, $description, $is_active]);
                
                $success = 'Template created successfully!';
                logAdminActivity($_SESSION['user_id'], 'create_email_template', 'email_template', $conn->lastInsertId(), "Created template: $name");
                break;
                
            case 'update_template':
                $template_id = (int)($_POST['template_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $subject = trim($_POST['subject'] ?? '');
                $body = trim($_POST['body'] ?? '');
                $variables = trim($_POST['variables'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($name) || empty($slug) || empty($subject) || empty($body)) {
                    $error = 'Name, slug, subject, and body are required!';
                    break;
                }
                
                $stmt = $conn->prepare("UPDATE email_templates SET name = ?, slug = ?, subject = ?, body = ?, variables = ?, description = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $subject, $body, $variables, $description, $is_active, $template_id]);
                
                $success = 'Template updated successfully!';
                logAdminActivity($_SESSION['user_id'], 'update_email_template', 'email_template', $template_id, "Updated template: $name");
                break;
                
            case 'delete_template':
                $template_id = (int)($_POST['template_id'] ?? 0);
                if ($template_id > 0) {
                    $stmt = $conn->prepare("DELETE FROM email_templates WHERE id = ?");
                    $stmt->execute([$template_id]);
                    $success = 'Template deleted successfully!';
                    logAdminActivity($_SESSION['user_id'], 'delete_email_template', 'email_template', $template_id, "Deleted template ID: $template_id");
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get all templates
try {
    $stmt = $conn->query("SELECT * FROM email_templates ORDER BY created_at DESC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $templates = [];
    $error = 'Error fetching templates: ' . $e->getMessage();
}

// Get template to edit
$edit_template = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    try {
        $stmt = $conn->prepare("SELECT * FROM email_templates WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_template = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Error fetching template: ' . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Email Templates Management</h1>
    <p>Create and manage email templates with variables</p>
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

<?php if ($edit_template): ?>
<!-- Edit Template Form -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Edit Email Template</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_template">
            <input type="hidden" name="template_id" value="<?php echo $edit_template['id']; ?>">
            
            <div class="form-group">
                <label>Template Name <span style="color: red;">*</span></label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_template['name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Slug <span style="color: red;">*</span></label>
                <input type="text" name="slug" class="form-control" value="<?php echo htmlspecialchars($edit_template['slug']); ?>" required>
                <small class="text-muted">Unique identifier (e.g., welcome-email, password-reset)</small>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($edit_template['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Email Subject <span style="color: red;">*</span></label>
                <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($edit_template['subject']); ?>" required>
                <small class="text-muted">Use variables like {name}, {email}, {site_name}</small>
            </div>
            
            <div class="form-group">
                <label>Available Variables</label>
                <textarea name="variables" class="form-control" rows="3" placeholder="List available variables, one per line (e.g., {name}, {email}, {site_name})"><?php echo htmlspecialchars($edit_template['variables'] ?? ''); ?></textarea>
                <small class="text-muted">One variable per line for documentation</small>
            </div>
            
            <div class="form-group">
                <label>Email Body (HTML) <span style="color: red;">*</span></label>
                <textarea name="body" class="form-control" rows="15" required style="font-family: monospace;"><?php echo htmlspecialchars($edit_template['body']); ?></textarea>
                <small class="text-muted">Use HTML for formatting. Variables: {name}, {email}, {site_name}, {site_url}, etc.</small>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" <?php echo $edit_template['is_active'] ? 'checked' : ''; ?>>
                    Active (template can be used)
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary">Update Template</button>
            <a href="email-templates.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
<?php else: ?>
<!-- Create Template Form -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Create New Email Template</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="create_template">
            
            <div class="form-group">
                <label>Template Name <span style="color: red;">*</span></label>
                <input type="text" name="name" class="form-control" required placeholder="e.g., Welcome Email">
            </div>
            
            <div class="form-group">
                <label>Slug <span style="color: red;">*</span></label>
                <input type="text" name="slug" class="form-control" required placeholder="e.g., welcome-email">
                <small class="text-muted">Unique identifier (auto-generated from name if empty)</small>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="What this template is used for"></textarea>
            </div>
            
            <div class="form-group">
                <label>Email Subject <span style="color: red;">*</span></label>
                <input type="text" name="subject" class="form-control" required placeholder="Welcome to {site_name}!">
                <small class="text-muted">Use variables like {name}, {email}, {site_name}</small>
            </div>
            
            <div class="form-group">
                <label>Available Variables</label>
                <textarea name="variables" class="form-control" rows="3" placeholder="{name}, {email}, {site_name}, {site_url}"></textarea>
                <small class="text-muted">Documentation: List available variables for this template</small>
            </div>
            
            <div class="form-group">
                <label>Email Body (HTML) <span style="color: red;">*</span></label>
                <textarea name="body" class="form-control" rows="15" required style="font-family: monospace;" placeholder="<html><body><h1>Hello {name}!</h1><p>Welcome to {site_name}...</p></body></html>"></textarea>
                <small class="text-muted">Use HTML for formatting. Standard variables: {name}, {email}, {site_name}, {site_url}</small>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" checked>
                    Active (template can be used)
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary">Create Template</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Templates List -->
<div class="card">
    <div class="card-header">
        <h2>All Email Templates</h2>
    </div>
    <div class="card-body">
        <?php if (empty($templates)): ?>
        <p>No templates created yet.</p>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $template): ?>
                <tr>
                    <td><?php echo $template['id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($template['name']); ?></strong><br>
                        <small style="color: #666;"><?php echo htmlspecialchars(substr($template['description'] ?? '', 0, 100)); ?></small>
                    </td>
                    <td><code><?php echo htmlspecialchars($template['slug']); ?></code></td>
                    <td><?php echo htmlspecialchars(substr($template['subject'], 0, 50)); ?>...</td>
                    <td>
                        <span class="badge badge-<?php echo $template['is_active'] ? 'success' : 'secondary'; ?>">
                            <?php echo $template['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($template['created_at'])); ?></td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <a href="?edit=<?php echo $template['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this template?');">
                                <input type="hidden" name="action" value="delete_template">
                                <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>


