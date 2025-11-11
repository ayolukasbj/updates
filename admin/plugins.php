<?php
/**
 * Plugin Management Page
 * Admin interface for managing plugins
 */

// Start output buffering to catch any errors
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly, we'll handle them
ini_set('log_errors', 1);

// Set custom error handler to catch fatal errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Plugins Page Error [$errno]: $errstr in $errfile:$errline");
    return false; // Let PHP handle it normally, but we've logged it
});

// Set shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        ob_clean();
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
        echo '<h1>An error occurred</h1>';
        echo '<p>Error: ' . htmlspecialchars($error['message']) . '</p>';
        echo '<p>File: ' . htmlspecialchars($error['file']) . ' Line: ' . $error['line'] . '</p>';
        echo '<p>Please check your error logs for more details.</p>';
        echo '</body></html>';
        exit;
    }
});

// Initialize error tracking
$init_error = '';
$page_title = 'Plugin Management';
$success = '';
$error = '';

// Load required files with error handling
try {
    if (!file_exists(__DIR__ . '/auth-check.php')) {
        throw new Exception('auth-check.php file not found');
    }
    require_once __DIR__ . '/auth-check.php';
} catch (Exception $e) {
    $init_error = 'Authentication check failed: ' . $e->getMessage();
    error_log("Plugins Page - auth-check.php error: " . $e->getMessage());
} catch (Error $e) {
    $init_error = 'Authentication check fatal error: ' . $e->getMessage();
    error_log("Plugins Page - auth-check.php fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}

try {
    if (!file_exists(__DIR__ . '/../config/database.php')) {
        throw new Exception('database.php file not found');
    }
    require_once __DIR__ . '/../config/database.php';
} catch (Exception $e) {
    $init_error = ($init_error ? $init_error . ' | ' : '') . 'Database config failed: ' . $e->getMessage();
    error_log("Plugins Page - database.php error: " . $e->getMessage());
} catch (Error $e) {
    $init_error = ($init_error ? $init_error . ' | ' : '') . 'Database config fatal error: ' . $e->getMessage();
    error_log("Plugins Page - database.php fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}

// Load plugin system with comprehensive error handling
$plugin_system_error = '';
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
        try {
            PluginLoader::init();
        } catch (Exception $e) {
            error_log("PluginLoader::init() error: " . $e->getMessage());
            $plugin_system_error = 'Plugin system initialization failed: ' . $e->getMessage();
        } catch (Error $e) {
            error_log("PluginLoader::init() fatal error: " . $e->getMessage());
            $plugin_system_error = 'Plugin system initialization failed. Please check error logs.';
        }
    } else {
        $plugin_system_error = 'PluginLoader class not found after loading plugin system files.';
    }
} catch (Exception $e) {
    error_log("Plugin system error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $plugin_system_error = 'Plugin system error: ' . $e->getMessage();
} catch (Error $e) {
    error_log("Plugin system fatal error: " . $e->getMessage());
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
    $plugin_system_error = 'Plugin system fatal error. Please check error logs.';
}

$page_title = 'Plugin Management';
$success = '';
$error = '';

// Handle plugin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $plugin_file = $_POST['plugin_file'] ?? '';
        
        if (empty($action) || empty($plugin_file)) {
            $error = 'Invalid request. Missing action or plugin file.';
        } elseif (!class_exists('PluginLoader')) {
            $error = 'Plugin system not loaded. Cannot perform plugin actions.';
        } elseif ($action === 'activate') {
            try {
                // Validate plugin file path
                if (empty($plugin_file)) {
                    $error = 'Plugin file path is empty.';
                } else {
                    // Normalize plugin file path - handle both relative and absolute paths
                    $plugin_file_original = $plugin_file;
                    $plugin_file = str_replace('\\', '/', trim($plugin_file));
                    
                    // Check if it's already an absolute path
                    $is_absolute = false;
                    if (preg_match('/^[A-Za-z]:/', $plugin_file) || strpos($plugin_file, '/') === 0) {
                        $is_absolute = true;
                    }
                    
                    // If absolute path, use it directly; otherwise construct relative path
                    if ($is_absolute) {
                        $plugin_path = $plugin_file;
                        // Convert to relative path for database storage
                        $base_path = str_replace('\\', '/', realpath(__DIR__ . '/../'));
                        if (strpos($plugin_path, $base_path) === 0) {
                            $plugin_file = substr($plugin_path, strlen($base_path) + 1);
                        }
                    } else {
                        // Remove any leading slashes or "plugins/" duplicates
                        $plugin_file = ltrim($plugin_file, '/');
                        if (strpos($plugin_file, 'plugins/') === 0) {
                            $plugin_file = substr($plugin_file, 8); // Remove "plugins/" prefix
                        }
                        
                        // Construct path from admin directory
                        $plugin_path = realpath(__DIR__ . '/../plugins/' . $plugin_file);
                        if (!$plugin_path) {
                            // Try with plugins/ prefix
                            $plugin_path = realpath(__DIR__ . '/../' . $plugin_file);
                        }
                    }
                    
                    // Final check - if still not found, try alternative paths
                    if (!$plugin_path || !file_exists($plugin_path)) {
                        $alt_paths = [
                            __DIR__ . '/../plugins/' . basename($plugin_file_original),
                            __DIR__ . '/../plugins/' . basename(dirname($plugin_file_original)) . '/' . basename($plugin_file_original),
                            realpath(__DIR__ . '/../plugins') . '/' . basename($plugin_file_original),
                        ];
                        
                        $found = false;
                        foreach ($alt_paths as $alt_path) {
                            if (file_exists($alt_path)) {
                                $plugin_path = $alt_path;
                                // Convert to relative path
                                $base_path = str_replace('\\', '/', realpath(__DIR__ . '/../'));
                                $plugin_path_normalized = str_replace('\\', '/', $plugin_path);
                                if (strpos($plugin_path_normalized, $base_path) === 0) {
                                    $plugin_file = substr($plugin_path_normalized, strlen($base_path) + 1);
                                }
                                $found = true;
                                error_log("Plugin activation: Found plugin at alternative path: {$plugin_path}, normalized to: {$plugin_file}");
                                break;
                            }
                        }
                        
                        if (!$found) {
                            $tried_paths = implode(', ', array_merge([$plugin_path], $alt_paths));
                            $error = 'Plugin file not found. Original: ' . htmlspecialchars($plugin_file_original) . '. Tried: ' . htmlspecialchars($tried_paths);
                            error_log("Plugin activation: File not found. Original: {$plugin_file_original}");
                        }
                    } else {
                        // Convert absolute path to relative for database
                        if ($is_absolute || strpos($plugin_path, realpath(__DIR__ . '/../')) === 0) {
                            $base_path = str_replace('\\', '/', realpath(__DIR__ . '/../'));
                            $plugin_path_normalized = str_replace('\\', '/', $plugin_path);
                            if (strpos($plugin_path_normalized, $base_path) === 0) {
                                $plugin_file = substr($plugin_path_normalized, strlen($base_path) + 1);
                            }
                        }
                        error_log("Plugin activation: Using plugin file: {$plugin_file} (path: {$plugin_path})");
                    }
                    
                    if (empty($error)) {
                        // Capture any output/errors during activation
                        ob_start();
                        $activation_result = PluginLoader::activatePlugin($plugin_file);
                        $activation_output = ob_get_clean();
                        
                        if ($activation_result === true) {
                            // Force reload plugins to update status immediately
                            if (class_exists('PluginLoader')) {
                                try {
                                    PluginLoader::init(true); // Force reload
                                } catch (Exception $e) {
                                    error_log("Error reloading plugins after activation: " . $e->getMessage());
                                }
                            }
                            
                            $success = 'Plugin activated successfully!';
                            if (!empty($activation_output)) {
                                error_log("Plugin activation output: " . $activation_output);
                            }
                            
                            // Redirect to refresh the page and show updated status
                            header("Location: " . $_SERVER['PHP_SELF'] . "?activated=1");
                            exit;
                        } else {
                            // Get more detailed error information
                            $last_error = error_get_last();
                            $error_msg = 'Failed to activate plugin.';
                            
                            // Check if there was output that might indicate an error
                            if (!empty($activation_output)) {
                                $error_msg .= ' Output: ' . htmlspecialchars(substr($activation_output, 0, 200));
                            }
                            
                            // Check last PHP error
                            if ($last_error) {
                                $error_msg .= ' PHP Error: ' . htmlspecialchars($last_error['message']);
                            }
                            
                            // Check database connection
                            try {
                                require_once __DIR__ . '/../config/database.php';
                                $db = new Database();
                                $conn = $db->getConnection();
                                if (!$conn) {
                                    $error_msg .= ' Database connection failed.';
                                } else {
                                    // Check if plugin exists in database
                                    $check_stmt = $conn->prepare("SELECT * FROM plugins WHERE plugin_file = ? OR plugin_file LIKE ?");
                                    $check_stmt->execute([$plugin_file, '%' . basename($plugin_file)]);
                                    $db_plugin = $check_stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($db_plugin) {
                                        $error_msg .= ' Plugin found in database with status: ' . htmlspecialchars($db_plugin['status'] ?? 'unknown');
                                    } else {
                                        $error_msg .= ' Plugin not found in database.';
                                    }
                                }
                            } catch (Exception $db_e) {
                                $error_msg .= ' Database check error: ' . htmlspecialchars($db_e->getMessage());
                            }
                            
                            $error_msg .= ' Plugin file: ' . htmlspecialchars($plugin_file);
                            $error = $error_msg;
                            error_log("Plugin activation failed. File: {$plugin_file}, Result: " . var_export($activation_result, true));
                        }
                    }
                }
            } catch (Exception $e) {
                $error = 'Failed to activate plugin: ' . htmlspecialchars($e->getMessage());
                error_log("Plugin activation exception: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
            } catch (Error $e) {
                $error = 'Failed to activate plugin: ' . htmlspecialchars($e->getMessage());
                error_log("Plugin activation fatal error: " . $e->getMessage());
                error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
            }
        } elseif ($action === 'deactivate') {
            if (PluginLoader::deactivatePlugin($plugin_file)) {
                $success = 'Plugin deactivated successfully!';
            } else {
                $error = 'Failed to deactivate plugin. Please check the error logs for details.';
            }
        } elseif ($action === 'delete') {
            if (PluginLoader::deletePlugin($plugin_file)) {
                $success = 'Plugin deleted successfully!';
            } else {
                $error = 'Failed to delete plugin. Please check the error logs for details.';
            }
        } else {
            $error = 'Invalid action specified.';
        }
    } catch (Exception $e) {
        error_log("Plugin action error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $error = 'An error occurred while processing your request: ' . htmlspecialchars($e->getMessage());
    } catch (Error $e) {
        error_log("Plugin action fatal error: " . $e->getMessage());
        error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
        $error = 'A fatal error occurred. Please check the error logs for details.';
    }
}

// Get all plugins
$all_plugins = [];
$active_plugins = [];

// Force reload if plugin was just activated
if (isset($_GET['activated']) && $_GET['activated'] == '1') {
    $success = 'Plugin activated successfully!';
}

try {
    if (class_exists('PluginLoader')) {
        // Force re-initialize to ensure all plugins are detected (especially after activation)
        try {
            PluginLoader::init(true); // Force reload
        } catch (Exception $e) {
            error_log("Error initializing PluginLoader: " . $e->getMessage());
        }
        
        try {
            $all_plugins = PluginLoader::getPlugins();
            $active_plugins = PluginLoader::getActivePlugins();
            error_log("Plugins page: Found " . count($all_plugins) . " plugins, " . count($active_plugins) . " active");
        } catch (Exception $e) {
            error_log("Error getting plugins from PluginLoader: " . $e->getMessage());
            $all_plugins = [];
            $active_plugins = [];
        }
        
        // Also check database for plugins that might not be detected by file scan
        try {
            $db = new Database();
            $conn = $db->getConnection();
            if ($conn) {
                $checkTable = $conn->query("SHOW TABLES LIKE 'plugins'");
                if ($checkTable->rowCount() > 0) {
                    $stmt = $conn->query("SELECT plugin_file, status FROM plugins");
                    $db_plugins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Add plugins from database that might not be in file system scan
                    foreach ($db_plugins as $db_plugin) {
                        $plugin_file = $db_plugin['plugin_file'];
                        $plugin_file_normalized = str_replace('\\', '/', $plugin_file);
                        
                        // Check if this plugin is already in $all_plugins
                        $found = false;
                        foreach ($all_plugins as $existing_plugin) {
                            $existing_file = str_replace('\\', '/', $existing_plugin['file'] ?? '');
                            if (strpos($existing_file, $plugin_file_normalized) !== false ||
                                strpos($plugin_file_normalized, $existing_file) !== false) {
                                $found = true;
                                break;
                            }
                        }
                        
                        // If not found and file exists, try to load it
                        if (!$found) {
                            // Try absolute path first
                            $absolute_path = realpath(__DIR__ . '/../' . ltrim($plugin_file, '/'));
                            if (!$absolute_path && file_exists($plugin_file)) {
                                $absolute_path = $plugin_file;
                            }
                            
                            if ($absolute_path && file_exists($absolute_path) && class_exists('PluginLoader')) {
                                try {
                                    // Get plugin data using PluginLoader method
                                    $plugin_data = PluginLoader::getPluginData($absolute_path);
                                    if ($plugin_data) {
                                        $plugin_slug = basename(dirname($absolute_path));
                                        $all_plugins[$plugin_slug] = [
                                            'file' => $absolute_path,
                                            'data' => $plugin_data,
                                            'folder' => $plugin_slug
                                        ];
                                    }
                                } catch (Exception $e) {
                                    error_log("Error loading plugin from database: " . $e->getMessage());
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error checking database for plugins: " . $e->getMessage());
        }
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

<?php if ($init_error): ?>
<div class="alert alert-danger" style="margin: 20px;">
    <i class="fas fa-exclamation-triangle"></i> <strong>Initialization Error:</strong> <?php echo htmlspecialchars($init_error); ?>
    <br><small>Please check your error logs and ensure all required files exist.</small>
</div>
<?php endif; ?>

<?php if ($plugin_system_error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle"></i> <strong>Plugin System Error:</strong> <?php echo htmlspecialchars($plugin_system_error); ?>
    <br><small>Some features may not be available. Please check your error logs for more details.</small>
</div>
<?php endif; ?>

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
                            $plugin_file = $plugin['file'] ?? '';
                            $plugin_file_normalized = str_replace('\\', '/', $plugin_file);
                            
                            // Check if active - normalize all paths for comparison
                            $is_active = false;
                            
                            // Check in active_plugins array (normalize paths)
                            foreach ($active_plugins as $active_file) {
                                $active_file_normalized = str_replace('\\', '/', $active_file);
                                if ($plugin_file_normalized === $active_file_normalized ||
                                    basename($plugin_file_normalized) === basename($active_file_normalized) ||
                                    strpos($plugin_file_normalized, $active_file_normalized) !== false ||
                                    strpos($active_file_normalized, $plugin_file_normalized) !== false) {
                                    $is_active = true;
                                    break;
                                }
                            }
                            
                            // Check in plugin_statuses (try multiple key variations)
                            if (!$is_active) {
                                // Try exact match
                                if (isset($plugin_statuses[$plugin_file]) && $plugin_statuses[$plugin_file] === 'active') {
                                    $is_active = true;
                                } elseif (isset($plugin_statuses[$plugin_file_normalized]) && $plugin_statuses[$plugin_file_normalized] === 'active') {
                                    $is_active = true;
                                } else {
                                    // Try fuzzy matching with all status keys
                                    foreach ($plugin_statuses as $status_file => $status) {
                                        $status_file_normalized = str_replace('\\', '/', $status_file);
                                        if ($status === 'active' && (
                                            $plugin_file_normalized === $status_file_normalized ||
                                            basename($plugin_file_normalized) === basename($status_file_normalized) ||
                                            strpos($plugin_file_normalized, $status_file_normalized) !== false ||
                                            strpos($status_file_normalized, $plugin_file_normalized) !== false
                                        )) {
                                            $is_active = true;
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            // Final check: query database directly if still not found
                            if (!$is_active && isset($conn)) {
                                try {
                                    $check_stmt = $conn->prepare("SELECT status FROM plugins WHERE plugin_file = ? OR plugin_file LIKE ? OR plugin_file LIKE ?");
                                    $plugin_basename = basename($plugin_file_normalized);
                                    $check_stmt->execute([$plugin_file, $plugin_file_normalized, '%' . $plugin_basename]);
                                    $db_status = $check_stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($db_status && $db_status['status'] === 'active') {
                                        $is_active = true;
                                    }
                                } catch (Exception $e) {
                                    // Ignore
                                }
                            }
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
                                    <?php 
                                    // Normalize plugin file path for form submission
                                    $form_plugin_file = $plugin['file'] ?? '';
                                    // If it's an absolute path, convert to relative
                                    if (!empty($form_plugin_file)) {
                                        $form_plugin_file_normalized = str_replace('\\', '/', $form_plugin_file);
                                        $base_path = str_replace('\\', '/', realpath(__DIR__ . '/../'));
                                        if (strpos($form_plugin_file_normalized, $base_path) === 0) {
                                            $form_plugin_file = substr($form_plugin_file_normalized, strlen($base_path) + 1);
                                        } elseif (preg_match('/^[A-Za-z]:/', $form_plugin_file_normalized)) {
                                            // It's an absolute path but not in our base directory
                                            // Extract just the relative part
                                            $form_plugin_file = 'plugins/' . basename(dirname($form_plugin_file_normalized)) . '/' . basename($form_plugin_file_normalized);
                                        }
                                        // Ensure it starts with plugins/ if it doesn't already
                                        if (strpos($form_plugin_file, 'plugins/') !== 0 && strpos($form_plugin_file, '/') !== 0) {
                                            $form_plugin_file = 'plugins/' . $form_plugin_file;
                                        }
                                    }
                                    ?>
                                    <input type="hidden" name="plugin_file" value="<?php echo htmlspecialchars($form_plugin_file); ?>">
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

