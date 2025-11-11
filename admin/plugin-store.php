<?php
/**
 * Plugin Store
 * Browse and install plugins from License Server
 */

require_once 'auth-check.php';
require_once __DIR__ . '/../config/database.php';

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

$page_title = 'Plugin Store';
$success = '';
$error = '';

// Handle license server URL configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_license_server'])) {
    try {
        if (!function_exists('update_option')) {
            throw new Exception('Plugin API not loaded');
        }
        
        $license_server_url = trim($_POST['license_server_url'] ?? '');
        
        if (!empty($license_server_url)) {
            update_option('license_server_url', $license_server_url);
            $success = 'License server URL saved successfully!';
        }
    } catch (Exception $e) {
        $error = 'Error saving license server URL: ' . $e->getMessage();
    }
}

// Get license key and license server URL
$license_key = '';
$license_server_url = '';

try {
    // First, try to get from config constant (set during installation)
    if (defined('LICENSE_SERVER_URL') && !empty(LICENSE_SERVER_URL)) {
        $license_server_url = LICENSE_SERVER_URL;
    }
    
    // If not in config, try to get from database
    if (empty($license_server_url)) {
        if (function_exists('get_option')) {
            $license_key = get_option('license_key', '');
            $license_server_url = get_option('license_server_url', '');
        } else {
            // Fallback to direct database query
            $db = new Database();
            $conn = $db->getConnection();
            if ($conn) {
                $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('license_key', 'license_server_url')");
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($results as $row) {
                    if ($row['setting_key'] === 'license_key') {
                        $license_key = $row['setting_value'];
                    } elseif ($row['setting_key'] === 'license_server_url') {
                        $license_server_url = $row['setting_value'];
                    }
                }
            }
        }
    }
    
    // If still empty, auto-detect based on environment (same logic as install.php)
    if (empty($license_server_url)) {
        $is_local = (
            $_SERVER['HTTP_HOST'] === 'localhost' || 
            strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
            strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
            strpos($_SERVER['HTTP_HOST'], '.local') !== false
        );
        
        if ($is_local) {
            $license_server_url = 'http://localhost/license-server';
        } else {
            $license_server_url = 'https://hylinktech.com/server';
        }
        
        // Save the auto-detected URL to database for future use
        try {
            if (function_exists('update_option')) {
                update_option('license_server_url', $license_server_url);
            } else {
                $db = new Database();
                $conn = $db->getConnection();
                if ($conn) {
                    $stmt = $conn->prepare("
                        INSERT INTO settings (setting_key, setting_value) 
                        VALUES ('license_server_url', ?) 
                        ON DUPLICATE KEY UPDATE setting_value = ?
                    ");
                    $stmt->execute([$license_server_url, $license_server_url]);
                }
            }
        } catch (Exception $e) {
            error_log("Error saving auto-detected license server URL: " . $e->getMessage());
        }
    }
    
    // Get license key if not already retrieved
    if (empty($license_key)) {
        if (function_exists('get_option')) {
            $license_key = get_option('license_key', '');
        } else {
            if (!isset($conn)) {
                $db = new Database();
                $conn = $db->getConnection();
            }
            if ($conn) {
                $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'license_key'");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $license_key = $result['setting_value'];
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Error loading license settings: " . $e->getMessage());
}

// Handle plugin installation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_plugin'])) {
    try {
        $plugin_slug = $_POST['plugin_slug'] ?? '';
        $plugin_name = $_POST['plugin_name'] ?? '';
        
        if (empty($plugin_slug) || empty($license_server_url)) {
            throw new Exception('Plugin slug or license server URL not configured');
        }
        
        // Download plugin from license server
        $download_url = rtrim($license_server_url, '/') . '/api/plugin-store.php?action=download&plugin_slug=' . urlencode($plugin_slug);
        if (!empty($license_key)) {
            $download_url .= '&license_key=' . urlencode($license_key);
        }
        
        $plugin_zip = file_get_contents($download_url);
        
        if ($plugin_zip === false) {
            throw new Exception('Failed to download plugin from license server');
        }
        
        // Save to temp file
        $temp_file = sys_get_temp_dir() . '/' . $plugin_slug . '_' . time() . '.zip';
        file_put_contents($temp_file, $plugin_zip);
        
        // Extract to plugins directory
        $plugins_dir = __DIR__ . '/../plugins/';
        if (!is_dir($plugins_dir)) {
            mkdir($plugins_dir, 0755, true);
        }
        
        $extract_dir = $plugins_dir . $plugin_slug . '/';
        if (is_dir($extract_dir)) {
            // Remove existing
            array_map('unlink', glob($extract_dir . '*'));
            rmdir($extract_dir);
        }
        mkdir($extract_dir, 0755, true);
        
        $zip = new ZipArchive();
        if ($zip->open($temp_file) === TRUE) {
            $zip->extractTo($extract_dir);
            $zip->close();
            unlink($temp_file);
            
            // Check if ZIP contained a folder or files directly
            $plugin_file = null;
            
            // Check if there's a folder with plugin slug
            if (is_dir($extract_dir . $plugin_slug)) {
                $plugin_folder = $extract_dir . $plugin_slug;
                $plugin_file = $plugin_folder . '/' . $plugin_slug . '.php';
            } else {
                // Files are in root
                $plugin_file = $extract_dir . $plugin_slug . '.php';
            }
            
            // If main plugin file not found, search for any PHP file
            if (!file_exists($plugin_file)) {
                $php_files = glob($extract_dir . '**/*.php', GLOB_BRACE);
                if (empty($php_files)) {
                    $php_files = glob($extract_dir . '*.php');
                }
                if (!empty($php_files)) {
                    $plugin_file = $php_files[0];
                    // If file is in a subdirectory, ensure proper structure
                    $relative_path = str_replace($extract_dir, '', $plugin_file);
                    if (strpos($relative_path, '/') !== false) {
                        // File is in subdirectory, this is correct structure
                    } else {
                        // File is in root, move to plugin folder
                        $proper_dir = $extract_dir . $plugin_slug . '/';
                        if (!is_dir($proper_dir)) {
                            mkdir($proper_dir, 0755, true);
                        }
                        $new_path = $proper_dir . basename($plugin_file);
                        rename($plugin_file, $new_path);
                        $plugin_file = $new_path;
                    }
                }
            }
            
            if (file_exists($plugin_file)) {
                if (!class_exists('PluginLoader')) {
                    throw new Exception('Plugin system not loaded. Cannot activate plugin.');
                }
                
                // Normalize plugin file path (use relative path from plugins directory)
                $plugins_base = realpath(__DIR__ . '/../plugins/');
                $plugin_file_normalized = $plugin_file;
                
                // Convert to relative path if absolute
                if (strpos($plugin_file, $plugins_base) === 0) {
                    $plugin_file_normalized = str_replace($plugins_base . DIRECTORY_SEPARATOR, '', $plugin_file);
                    $plugin_file_normalized = str_replace('\\', '/', $plugin_file_normalized);
                    $plugin_file_normalized = 'plugins/' . $plugin_file_normalized;
                }
                
                // Activate the plugin (this saves it to database)
                if (PluginLoader::activatePlugin($plugin_file_normalized)) {
                    // Force reload plugins to detect the newly installed one
                    try {
                        PluginLoader::init(true); // Force reload
                    } catch (Exception $e) {
                        error_log("Error reloading plugins after installation: " . $e->getMessage());
                        // Continue anyway, plugin is already activated
                    }
                    $success = 'Plugin "' . htmlspecialchars($plugin_name) . '" installed and activated successfully!';
                } else {
                    throw new Exception('Failed to activate plugin. Please try activating it manually from the Plugins page.');
                }
            } else {
                throw new Exception('Plugin file not found after extraction: ' . $plugin_file);
            }
        } else {
            throw new Exception('Failed to extract plugin ZIP file');
        }
    } catch (Exception $e) {
        $error = 'Installation error: ' . $e->getMessage();
    }
}

// Fetch available plugins from license server
$available_plugins = [];
if (!empty($license_server_url)) {
    try {
        $store_url = rtrim($license_server_url, '/') . '/api/plugin-store.php?action=list';
        if (!empty($license_key)) {
            $store_url .= '&license_key=' . urlencode($license_key);
        }
        
        $response = @file_get_contents($store_url);
        if ($response !== false) {
            $data = json_decode($response, true);
            if ($data && $data['success'] && isset($data['plugins'])) {
                $available_plugins = $data['plugins'];
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching plugins from store: " . $e->getMessage());
    }
}

// Get installed plugins
$installed_plugins = [];
$installed_plugins_by_file = []; // Index by file path for better matching

try {
    // First, check database directly for installed plugins
    $db = new Database();
    $conn = $db->getConnection();
    if ($conn) {
        try {
            $checkTable = $conn->query("SHOW TABLES LIKE 'plugins'");
            if ($checkTable->rowCount() > 0) {
                $stmt = $conn->query("SELECT plugin_file, status FROM plugins");
                $db_plugins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($db_plugins as $db_plugin) {
                    $plugin_file = $db_plugin['plugin_file'];
                    $plugin_file_normalized = str_replace('\\', '/', $plugin_file);
                    
                    // Extract plugin slug from file path
                    // Format: plugins/plugin-slug/plugin-slug.php or plugins/plugin-slug/plugin.php
                    if (preg_match('/plugins\/([^\/]+)\//', $plugin_file_normalized, $matches)) {
                        $plugin_slug = $matches[1];
                        $installed_plugins_by_file[$plugin_file] = $plugin_slug;
                        $installed_plugins_by_file[$plugin_file_normalized] = $plugin_slug;
                        
                        // Also try without plugins/ prefix
                        $plugin_file_no_prefix = preg_replace('/^plugins\//', '', $plugin_file_normalized);
                        $installed_plugins_by_file[$plugin_file_no_prefix] = $plugin_slug;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error reading plugins from database: " . $e->getMessage());
        }
    }
    
    // Then, get plugins from PluginLoader
    if (class_exists('PluginLoader')) {
        // Force re-initialize to ensure plugins are loaded (especially after installation)
        try {
            PluginLoader::init(true); // Force reload
        } catch (Exception $e) {
            error_log("Error initializing PluginLoader: " . $e->getMessage());
        }
        
        try {
            $all_plugins = PluginLoader::getPlugins();
            $active_plugins = PluginLoader::getActivePlugins();
        } catch (Exception $e) {
            error_log("Error getting plugins from PluginLoader: " . $e->getMessage());
            $all_plugins = [];
            $active_plugins = [];
        }
        
        foreach ($all_plugins as $plugin_slug => $plugin) {
            // Normalize plugin file path for comparison
            $plugin_file = $plugin['file'] ?? '';
            $plugin_file_normalized = str_replace('\\', '/', $plugin_file);
            
            // Check if this plugin is in database (by file path)
            $is_in_db = false;
            $db_status = 'inactive';
            foreach ($installed_plugins_by_file as $db_file => $db_slug) {
                if (strpos($plugin_file_normalized, $db_file) !== false || 
                    strpos($db_file, $plugin_file_normalized) !== false ||
                    basename(dirname($plugin_file_normalized)) === basename(dirname($db_file))) {
                    $is_in_db = true;
                    // Get status from database
                    try {
                        $statusStmt = $conn->prepare("SELECT status FROM plugins WHERE plugin_file = ? OR plugin_file LIKE ?");
                        $statusStmt->execute([$db_file, '%' . basename($plugin_file_normalized)]);
                        $statusResult = $statusStmt->fetch(PDO::FETCH_ASSOC);
                        if ($statusResult) {
                            $db_status = $statusResult['status'];
                        }
                    } catch (Exception $e) {
                        // Ignore
                    }
                    break;
                }
            }
            
            // Use folder name as key (plugin slug)
            $installed_plugins[$plugin_slug] = [
                'name' => $plugin['data']['Name'] ?? $plugin_slug,
                'active' => in_array($plugin_file, $active_plugins) || 
                           in_array($plugin_file_normalized, $active_plugins) ||
                           ($is_in_db && $db_status === 'active'),
                'file' => $plugin_file,
                'file_normalized' => $plugin_file_normalized,
                'in_database' => $is_in_db
            ];
        }
    }
} catch (Exception $e) {
    error_log("Error getting installed plugins: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-store"></i> Plugin Store</h1>
    <p>Browse and install plugins from the License Server plugin store.</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<?php if ($plugin_system_error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle"></i> <strong>Plugin System Error:</strong> <?php echo htmlspecialchars($plugin_system_error); ?>
    <br><small>Some features may not be available. Please check your error logs for more details.</small>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- License Server Configuration -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>License Server Configuration</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label>License Server URL</label>
                <input type="url" name="license_server_url" class="form-control" 
                       value="<?php echo htmlspecialchars($license_server_url); ?>" 
                       placeholder="http://your-license-server.com">
                <small style="color: #6b7280;">Enter the URL of your license server to access the plugin store.</small>
            </div>
            <button type="submit" name="save_license_server" class="btn btn-primary">Save</button>
        </form>
    </div>
</div>

<?php if (empty($license_server_url)): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> 
    <strong>License Server URL not configured.</strong> 
    Please configure your license server URL above to access the plugin store.
</div>
<?php elseif (empty($available_plugins)): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> 
    No plugins available in the store, or unable to connect to license server.
    <br>License Server URL: <code><?php echo htmlspecialchars($license_server_url); ?></code>
</div>
<?php else: ?>
<div class="card">
    <div class="card-header">
        <h2>Available Plugins</h2>
    </div>
    <div class="card-body">
        <div class="plugin-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
            <?php foreach ($available_plugins as $plugin): 
                // Check if plugin is installed by matching slug with folder name
                $is_installed = false;
                $is_active = false;
                $plugin_slug = $plugin['plugin_slug'] ?? '';
                
                // Check by exact slug match first
                if (isset($installed_plugins[$plugin_slug])) {
                    $is_installed = true;
                    $is_active = $installed_plugins[$plugin_slug]['active'];
                } else {
                    // Check by folder name or file path
                    foreach ($installed_plugins as $installed_slug => $installed_data) {
                        $plugin_file = $installed_data['file'] ?? '';
                        $plugin_file_normalized = $installed_data['file_normalized'] ?? '';
                        
                        // Check if plugin slug matches folder name or is in file path
                        if (strpos($plugin_file, $plugin_slug) !== false || 
                            strpos($plugin_file_normalized, $plugin_slug) !== false ||
                            basename(dirname($plugin_file)) === $plugin_slug ||
                            basename(dirname($plugin_file_normalized)) === $plugin_slug ||
                            $installed_slug === $plugin_slug) {
                            $is_installed = true;
                            $is_active = $installed_data['active'];
                            break;
                        }
                    }
                    
                    // Also check database directly if not found
                    if (!$is_installed && isset($conn)) {
                        try {
                            $checkStmt = $conn->prepare("SELECT status FROM plugins WHERE plugin_file LIKE ?");
                            $checkStmt->execute(['%' . $plugin_slug . '%']);
                            $db_result = $checkStmt->fetch(PDO::FETCH_ASSOC);
                            if ($db_result) {
                                $is_installed = true;
                                $is_active = ($db_result['status'] === 'active');
                            }
                        } catch (Exception $e) {
                            // Ignore
                        }
                    }
                }
            ?>
            <div class="plugin-card" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px;">
                <h3 style="margin: 0 0 10px 0;"><?php echo htmlspecialchars($plugin['plugin_name']); ?></h3>
                <p style="color: #6b7280; margin: 5px 0;">
                    <strong>Version:</strong> <?php echo htmlspecialchars($plugin['version']); ?>
                </p>
                <p style="color: #6b7280; margin: 10px 0;">
                    <?php echo htmlspecialchars($plugin['description'] ?: 'No description'); ?>
                </p>
                <p style="font-size: 12px; color: #9ca3af; margin: 10px 0;">
                    <strong>Author:</strong> <?php echo htmlspecialchars($plugin['author'] ?: 'Unknown'); ?>
                </p>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                    <?php if ($is_installed): ?>
                        <?php if ($is_active): ?>
                            <span class="badge badge-success">Installed & Active</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Installed (Inactive)</span>
                            <a href="plugins.php" class="btn btn-success btn-sm" style="margin-left: 10px;">Activate</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="plugin_slug" value="<?php echo htmlspecialchars($plugin['plugin_slug']); ?>">
                            <input type="hidden" name="plugin_name" value="<?php echo htmlspecialchars($plugin['plugin_name']); ?>">
                            <button type="submit" name="install_plugin" class="btn btn-primary btn-sm">
                                <i class="fas fa-download"></i> Install
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

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

