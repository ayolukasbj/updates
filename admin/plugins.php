<?php
/**
 * Plugin Management Page
 * Admin interface for managing plugins
 */

require_once 'auth-check.php';
require_once __DIR__ . '/../config/database.php';

// Load plugin system with error handling
try {
    if (file_exists(__DIR__ . '/../includes/plugin-loader.php')) {
        require_once __DIR__ . '/../includes/plugin-loader.php';
    } else {
        throw new Exception('Plugin loader file not found');
    }
    
    if (file_exists(__DIR__ . '/../includes/plugin-api.php')) {
        require_once __DIR__ . '/../includes/plugin-api.php';
    } else {
        throw new Exception('Plugin API file not found');
    }
    
    // Initialize plugin system
    if (class_exists('PluginLoader')) {
        PluginLoader::init();
    }
} catch (Exception $e) {
    error_log("Plugin system error: " . $e->getMessage());
    die("Plugin system error: " . $e->getMessage());
}

$page_title = 'Plugin Management';
$success = '';
$error = '';

// Handle plugin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $plugin_file = $_POST['plugin_file'] ?? '';
    
    if ($action === 'activate') {
        if (PluginLoader::activatePlugin($plugin_file)) {
            $success = 'Plugin activated successfully!';
        } else {
            $error = 'Failed to activate plugin.';
        }
    } elseif ($action === 'deactivate') {
        if (PluginLoader::deactivatePlugin($plugin_file)) {
            $success = 'Plugin deactivated successfully!';
        } else {
            $error = 'Failed to deactivate plugin.';
        }
    } elseif ($action === 'delete') {
        if (PluginLoader::deletePlugin($plugin_file)) {
            $success = 'Plugin deleted successfully!';
        } else {
            $error = 'Failed to delete plugin.';
        }
    }
}

// Get all plugins
$all_plugins = [];
$active_plugins = [];

try {
    if (class_exists('PluginLoader')) {
        // Force re-initialize to ensure all plugins are detected
        PluginLoader::init(true); // Force reload
        
        $all_plugins = PluginLoader::getPlugins();
        $active_plugins = PluginLoader::getActivePlugins();
    }
} catch (Exception $e) {
    error_log("Error getting plugins: " . $e->getMessage());
    $error = 'Error loading plugins: ' . $e->getMessage();
}

// Get plugin status from database
$plugin_statuses = [];
try {
    $db = new Database();
    $conn = $db->getConnection();
    if ($conn) {
        // Check if plugins table exists
        $stmt = $conn->query("SHOW TABLES LIKE 'plugins'");
        if ($stmt->rowCount() > 0) {
            $stmt = $conn->query("SELECT plugin_file, status FROM plugins");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as $row) {
                $plugin_statuses[$row['plugin_file']] = $row['status'];
            }
        }
    }
} catch (Exception $e) {
    error_log("Error loading plugin statuses: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-puzzle-piece"></i> Plugin Management</h1>
    <p>Manage and activate third-party plugins to extend your music platform functionality.</p>
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
        <h2>Installed Plugins</h2>
    </div>
    <div class="card-body">
        <?php if (empty($all_plugins)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No plugins installed. Upload plugins to the <code>plugins/</code> directory.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Plugin</th>
                            <th>Description</th>
                            <th>Version</th>
                            <th>Author</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_plugins as $plugin_slug => $plugin): 
                            $plugin_data = $plugin['data'];
                            $is_active = in_array($plugin['file'], $active_plugins) || 
                                        (isset($plugin_statuses[$plugin['file']]) && $plugin_statuses[$plugin['file']] === 'active');
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($plugin_data['Name'] ?: $plugin_slug); ?></strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($plugin_data['Description'] ?: 'No description'); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($plugin_data['Version'] ?: '1.0.0'); ?>
                            </td>
                            <td>
                                <?php if (!empty($plugin_data['AuthorURI'])): ?>
                                    <a href="<?php echo htmlspecialchars($plugin_data['AuthorURI']); ?>" target="_blank">
                                        <?php echo htmlspecialchars($plugin_data['Author'] ?: 'Unknown'); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($plugin_data['Author'] ?: 'Unknown'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_active): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="plugin_file" value="<?php echo htmlspecialchars($plugin['file']); ?>">
                                    <?php if ($is_active): ?>
                                        <button type="submit" name="action" value="deactivate" class="btn btn-warning btn-sm" 
                                                onclick="return confirm('Are you sure you want to deactivate this plugin?');">
                                            <i class="fas fa-power-off"></i> Deactivate
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="activate" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Activate
                                        </button>
                                    <?php endif; ?>
                                    <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" 
                                            onclick="return confirm('Are you sure you want to delete this plugin? This action cannot be undone!');">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top: 30px;">
    <div class="card-header">
        <h2><i class="fas fa-info-circle"></i> Plugin Development</h2>
    </div>
    <div class="card-body">
        <h3>Creating a Plugin</h3>
        <p>To create a plugin:</p>
        <ol>
            <li>Create a folder in the <code>plugins/</code> directory with your plugin name</li>
            <li>Create a PHP file with the same name as the folder</li>
            <li>Add plugin header information at the top of the file</li>
            <li>Use hooks and filters to extend functionality</li>
        </ol>
        
        <h4>Plugin Header Example:</h4>
        <pre><code>&lt;?php
/**
 * Plugin Name: My Awesome Plugin
 * Plugin URI: https://example.com/my-plugin
 * Description: This is a sample plugin description
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: my-plugin
 */

// Your plugin code here</code></pre>
        
        <h4>Available Hooks:</h4>
        <ul>
            <li><strong>Actions:</strong> <code>do_action('hook_name', $arg1, $arg2)</code></li>
            <li><strong>Filters:</strong> <code>apply_filters('filter_name', $value, $arg1)</code></li>
        </ul>
        
        <p><a href="https://github.com/your-repo/plugin-docs" target="_blank" class="btn btn-primary">
            <i class="fas fa-book"></i> View Plugin Documentation
        </a></p>
    </div>
</div>

<style>
.badge {
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}
.badge-success {
    background: #28a745;
    color: white;
}
.badge-secondary {
    background: #6c757d;
    color: white;
}
</style>

<?php include 'includes/footer.php'; ?>

