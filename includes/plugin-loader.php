<?php
/**
 * Plugin Loader System
 * WordPress-like plugin architecture for Music Platform
 * 
 * This system provides:
 * - Action hooks (do_action)
 * - Filter hooks (apply_filters)
 * - Plugin management
 * - Plugin API
 */

class PluginLoader {
    private static $instance = null;
    private static $plugins = [];
    private static $active_plugins = [];
    private static $hooks = [
        'actions' => [],
        'filters' => []
    ];
    private static $admin_menus = [];
    private static $admin_submenus = [];
    private static $loaded = false;
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize plugin system
     * @param bool $force_reload Force reload even if already loaded
     */
    public static function init($force_reload = false) {
        if (self::$loaded && !$force_reload) {
            return;
        }
        
        try {
            // Reset if forcing reload
            if ($force_reload) {
                self::$plugins = [];
                self::$active_plugins = [];
                self::$loaded = false;
            }
            
            // Load active plugins from database
            self::loadActivePlugins();
            
            // Load all plugins
            self::loadPlugins();
            
            // Activate active plugins (only if we have plugins loaded)
            if (!empty(self::$plugins)) {
                self::activatePlugins();
            }
            
            self::$loaded = true;
        } catch (Exception $e) {
            error_log("PluginLoader::init() error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            // Don't set loaded to true if init failed
        } catch (Error $e) {
            error_log("PluginLoader::init() fatal error: " . $e->getMessage());
            error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
            error_log("Stack trace: " . $e->getTraceAsString());
            // Don't set loaded to true if init failed
        }
    }
    
    /**
     * Load active plugins from database
     */
    private static function loadActivePlugins() {
        try {
            // Check if database.php is already loaded
            if (!class_exists('Database')) {
                if (file_exists(__DIR__ . '/../config/database.php')) {
                    require_once __DIR__ . '/../config/database.php';
                } else {
                    return; // Database config not found, skip loading
                }
            }
            
            $db = new Database();
            $conn = $db->getConnection();
            
            if ($conn) {
                // Check if plugins table exists
                try {
                    $stmt = $conn->query("SHOW TABLES LIKE 'plugins'");
                    if ($stmt && $stmt->rowCount() > 0) {
                        $stmt = $conn->query("SELECT plugin_file FROM plugins WHERE status = 'active'");
                        if ($stmt) {
                            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($results as $row) {
                                self::$active_plugins[] = $row['plugin_file'];
                            }
                        }
                    }
                } catch (PDOException $e) {
                    // Table doesn't exist yet, that's okay
                    error_log("Plugins table not found (will be created on first use): " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            error_log("Plugin Loader Error: " . $e->getMessage());
        }
    }
    
    /**
     * Scan and load all plugins
     */
    private static function loadPlugins() {
        try {
            $plugins_dir = __DIR__ . '/../plugins';
            
            if (!is_dir($plugins_dir)) {
                @mkdir($plugins_dir, 0755, true);
                return;
            }
            
            $plugin_folders = glob($plugins_dir . '/*', GLOB_ONLYDIR);
            
            if ($plugin_folders === false) {
                error_log("Error scanning plugins directory");
                return;
            }
            
            foreach ($plugin_folders as $plugin_folder) {
                try {
                    $plugin_file = $plugin_folder . '/' . basename($plugin_folder) . '.php';
                    
                    if (file_exists($plugin_file)) {
                        $plugin_data = self::getPluginData($plugin_file);
                        if ($plugin_data) {
                            self::$plugins[basename($plugin_folder)] = [
                                'file' => $plugin_file,
                                'data' => $plugin_data,
                                'folder' => basename($plugin_folder)
                            ];
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error loading plugin from {$plugin_folder}: " . $e->getMessage());
                    continue; // Skip this plugin and continue with others
                } catch (Error $e) {
                    error_log("Fatal error loading plugin from {$plugin_folder}: " . $e->getMessage());
                    continue; // Skip this plugin and continue with others
                }
            }
        } catch (Exception $e) {
            error_log("Error in loadPlugins(): " . $e->getMessage());
        } catch (Error $e) {
            error_log("Fatal error in loadPlugins(): " . $e->getMessage());
        }
    }
    
    /**
     * Get plugin header data
     */
    public static function getPluginData($plugin_file) {
        try {
            $default_headers = [
                'Name' => 'Plugin Name',
                'PluginURI' => 'Plugin URI',
                'Version' => 'Version',
                'Description' => 'Description',
                'Author' => 'Author',
                'AuthorURI' => 'Author URI',
                'TextDomain' => 'Text Domain',
                'DomainPath' => 'Domain Path',
                'Network' => 'Network',
                'RequiresWP' => 'Requires at least',
                'TestedWP' => 'Tested up to',
                'RequiresPHP' => 'Requires PHP',
                'UpdateURI' => 'Update URI',
            ];
            
            $plugin_data = [];
            $file_content = @file_get_contents($plugin_file);
            
            if ($file_content === false) {
                error_log("Could not read plugin file: {$plugin_file}");
                return null;
            }
            
            foreach ($default_headers as $field => $regex) {
                try {
                    if (preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi', $file_content, $match) && $match[1]) {
                        $plugin_data[$field] = trim(preg_replace("/\s*(?:\*\/|\?>).*/", '', $match[1]));
                    } else {
                        $plugin_data[$field] = '';
                    }
                } catch (Exception $e) {
                    $plugin_data[$field] = '';
                }
            }
            
            // Add file path
            $plugin_data['File'] = $plugin_file;
            $plugin_data['Folder'] = dirname($plugin_file);
            
            return $plugin_data;
        } catch (Exception $e) {
            error_log("Error getting plugin data from {$plugin_file}: " . $e->getMessage());
            return null;
        } catch (Error $e) {
            error_log("Fatal error getting plugin data from {$plugin_file}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Activate active plugins
     */
    private static function activatePlugins() {
        try {
            foreach (self::$active_plugins as $plugin_file) {
                // Find plugin by file name
                foreach (self::$plugins as $plugin) {
                    if (basename($plugin['file']) === basename($plugin_file) || 
                        $plugin['file'] === $plugin_file ||
                        strpos($plugin['file'], $plugin_file) !== false) {
                        self::loadPlugin($plugin['file']);
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error activating plugins: " . $e->getMessage());
        } catch (Error $e) {
            error_log("Fatal error activating plugins: " . $e->getMessage());
        }
    }
    
    /**
     * Load a plugin file
     */
    private static function loadPlugin($plugin_file) {
        if (!file_exists($plugin_file)) {
            error_log("Plugin file not found: {$plugin_file}");
            return;
        }
        
        // Skip loading if file is empty or too small
        if (filesize($plugin_file) < 10) {
            error_log("Skipping empty plugin file: {$plugin_file}");
            return;
        }
        
        try {
            // Check if plugin API functions are available before loading
            // Some plugins might need these functions
            if (!function_exists('add_action') && file_exists(__DIR__ . '/plugin-api.php')) {
                require_once __DIR__ . '/plugin-api.php';
            }
            
            // Suppress errors during plugin loading to prevent fatal errors from breaking the page
            $old_error_handler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
                // Only suppress warnings and notices, not errors
                if ($errno === E_WARNING || $errno === E_NOTICE) {
                    error_log("Plugin loading warning in {$errfile}:{$errline} - {$errstr}");
                    return true; // Suppress warning/notice
                }
                return false; // Let errors through
            });
            
            // Use include instead of require_once to allow continuation on error
            @include $plugin_file;
            
            // Restore error handler
            if ($old_error_handler !== null) {
                set_error_handler($old_error_handler);
            } else {
                restore_error_handler();
            }
        } catch (ParseError $e) {
            error_log("Parse error loading plugin {$plugin_file}: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        } catch (Exception $e) {
            error_log("Error loading plugin {$plugin_file}: " . $e->getMessage());
        } catch (Error $e) {
            error_log("Fatal error loading plugin {$plugin_file}: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        } catch (Throwable $e) {
            error_log("Throwable error loading plugin {$plugin_file}: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        }
    }
    
    /**
     * Register an action hook
     */
    public static function addAction($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        if (!isset(self::$hooks['actions'][$hook_name])) {
            self::$hooks['actions'][$hook_name] = [];
        }
        
        self::$hooks['actions'][$hook_name][] = [
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args
        ];
        
        // Sort by priority
        usort(self::$hooks['actions'][$hook_name], function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
    }
    
    /**
     * Register a filter hook
     */
    public static function addFilter($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        if (!isset(self::$hooks['filters'][$hook_name])) {
            self::$hooks['filters'][$hook_name] = [];
        }
        
        self::$hooks['filters'][$hook_name][] = [
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args
        ];
        
        // Sort by priority
        usort(self::$hooks['filters'][$hook_name], function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
    }
    
    /**
     * Execute an action hook
     */
    public static function doAction($hook_name, ...$args) {
        if (!isset(self::$hooks['actions'][$hook_name])) {
            return;
        }
        
        foreach (self::$hooks['actions'][$hook_name] as $hook) {
            $callback = $hook['callback'];
            $accepted_args = $hook['accepted_args'];
            
            if (is_callable($callback)) {
                $args_to_pass = array_slice($args, 0, $accepted_args);
                call_user_func_array($callback, $args_to_pass);
            }
        }
    }
    
    /**
     * Apply a filter hook
     */
    public static function applyFilters($hook_name, $value, ...$args) {
        if (!isset(self::$hooks['filters'][$hook_name])) {
            return $value;
        }
        
        foreach (self::$hooks['filters'][$hook_name] as $hook) {
            $callback = $hook['callback'];
            $accepted_args = $hook['accepted_args'];
            
            if (is_callable($callback)) {
                $args_to_pass = array_merge([$value], array_slice($args, 0, $accepted_args - 1));
                $value = call_user_func_array($callback, $args_to_pass);
            }
        }
        
        return $value;
    }
    
    /**
     * Get all plugins
     */
    public static function getPlugins() {
        return self::$plugins;
    }
    
    /**
     * Get active plugins
     */
    public static function getActivePlugins() {
        return self::$active_plugins;
    }
    
    /**
     * Activate a plugin
     */
    public static function activatePlugin($plugin_file) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = new Database();
            $conn = $db->getConnection();
            
            if ($conn) {
                // Create plugins table if it doesn't exist
                self::createPluginsTable($conn);
                
                // Check if plugin exists
                $stmt = $conn->prepare("SELECT * FROM plugins WHERE plugin_file = ?");
                $stmt->execute([$plugin_file]);
                $plugin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($plugin) {
                    // Update status
                    $stmt = $conn->prepare("UPDATE plugins SET status = 'active', activated_at = NOW() WHERE plugin_file = ?");
                    $stmt->execute([$plugin_file]);
                } else {
                    // Insert new plugin
                    $stmt = $conn->prepare("INSERT INTO plugins (plugin_file, status, activated_at) VALUES (?, 'active', NOW())");
                    $stmt->execute([$plugin_file]);
                }
                
                // Reload plugins
                self::$active_plugins = [];
                self::loadActivePlugins();
                self::activatePlugins();
                
                return true;
            }
        } catch (Exception $e) {
            error_log("Error activating plugin: " . $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    /**
     * Deactivate a plugin
     */
    public static function deactivatePlugin($plugin_file) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = new Database();
            $conn = $db->getConnection();
            
            if ($conn) {
                $stmt = $conn->prepare("UPDATE plugins SET status = 'inactive', deactivated_at = NOW() WHERE plugin_file = ?");
                $stmt->execute([$plugin_file]);
                
                // Remove from active plugins
                self::$active_plugins = array_filter(self::$active_plugins, function($file) use ($plugin_file) {
                    return $file !== $plugin_file;
                });
                
                return true;
            }
        } catch (Exception $e) {
            error_log("Error deactivating plugin: " . $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    /**
     * Create plugins table
     */
    private static function createPluginsTable($conn) {
        $sql = "CREATE TABLE IF NOT EXISTS plugins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plugin_file VARCHAR(255) NOT NULL UNIQUE,
            status ENUM('active', 'inactive') DEFAULT 'inactive',
            activated_at DATETIME NULL,
            deactivated_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_plugin_file (plugin_file)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->exec($sql);
    }
    
    /**
     * Delete a plugin
     */
    public static function deletePlugin($plugin_file) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = new Database();
            $conn = $db->getConnection();
            
            if ($conn) {
                // Remove from database
                $stmt = $conn->prepare("DELETE FROM plugins WHERE plugin_file = ?");
                $stmt->execute([$plugin_file]);
                
                // Delete plugin folder
                $plugin_folder = dirname($plugin_file);
                if (is_dir($plugin_folder)) {
                    self::deleteDirectory($plugin_folder);
                }
                
                return true;
            }
        } catch (Exception $e) {
            error_log("Error deleting plugin: " . $e->getMessage());
            return false;
        }
        
        return false;
    }
    
    /**
     * Recursively delete directory
     */
    private static function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
    
    /**
     * Register an admin menu item
     */
    public static function registerAdminMenu($menu_data) {
        self::$admin_menus[] = $menu_data;
    }
    
    /**
     * Register an admin submenu item
     */
    public static function registerAdminSubmenu($submenu_data) {
        if (!isset(self::$admin_submenus[$submenu_data['parent_slug']])) {
            self::$admin_submenus[$submenu_data['parent_slug']] = [];
        }
        self::$admin_submenus[$submenu_data['parent_slug']][] = $submenu_data;
    }
    
    /**
     * Get all registered admin menu items
     */
    public static function getAdminMenus() {
        return self::$admin_menus;
    }
    
    /**
     * Get all registered admin submenu items for a parent
     */
    public static function getAdminSubmenus($parent_slug = null) {
        if ($parent_slug === null) {
            return self::$admin_submenus;
        }
        return isset(self::$admin_submenus[$parent_slug]) ? self::$admin_submenus[$parent_slug] : [];
    }
}

// Initialize plugin system (only if not already initialized)
// Note: We can't check $loaded directly as it's private, so we'll just try to init
// The init() method itself checks if already loaded
try {
    PluginLoader::init();
} catch (Exception $e) {
    error_log("Plugin system initialization error: " . $e->getMessage());
}

