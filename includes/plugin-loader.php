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
    private static $loaded_plugin_files = []; // Track which plugin files have been loaded
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
        
        // Normalize plugin file path for comparison
        $plugin_file_normalized = realpath($plugin_file);
        if (!$plugin_file_normalized) {
            $plugin_file_normalized = $plugin_file;
        }
        
        // Check if this plugin file has already been loaded
        if (isset(self::$loaded_plugin_files[$plugin_file_normalized])) {
            error_log("Plugin file already loaded, skipping: {$plugin_file}");
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
            
            // Use include_once to prevent double-loading, but catch errors
            @include_once $plugin_file;
            
            // Mark this plugin file as loaded
            self::$loaded_plugin_files[$plugin_file_normalized] = true;
            
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
            // Validate plugin file path
            if (empty($plugin_file)) {
                $error_msg = "Empty plugin file path";
                error_log("Plugin activation error: {$error_msg}");
                throw new Exception($error_msg);
            }
            
            // Normalize plugin file path - handle both relative and absolute paths
            $original_plugin_file = $plugin_file;
            $plugin_file = str_replace('\\', '/', trim($plugin_file));
            
            // Check if it's already an absolute path
            $is_absolute = false;
            if (preg_match('/^[A-Za-z]:/', $plugin_file) || strpos($plugin_file, '/') === 0) {
                $is_absolute = true;
            }
            
            // Determine full path
            if ($is_absolute) {
                $full_path = $plugin_file;
                // Convert to relative path for database storage
                $base_path = str_replace('\\', '/', realpath(__DIR__ . '/../'));
                $plugin_path_normalized = str_replace('\\', '/', $full_path);
                if (strpos($plugin_path_normalized, $base_path) === 0) {
                    $plugin_file = substr($plugin_path_normalized, strlen($base_path) + 1);
                }
            } else {
                // Remove any leading slashes or "plugins/" duplicates
                $plugin_file = ltrim($plugin_file, '/');
                if (strpos($plugin_file, 'plugins/') === 0) {
                    $plugin_file = substr($plugin_file, 8); // Remove "plugins/" prefix
                }
                
                // Construct path
                $full_path = realpath(__DIR__ . '/../plugins/' . $plugin_file);
                if (!$full_path) {
                    // Try with plugins/ prefix
                    $full_path = realpath(__DIR__ . '/../' . $plugin_file);
                }
                
                // If found, normalize plugin_file to relative path
                if ($full_path) {
                    $base_path = str_replace('\\', '/', realpath(__DIR__ . '/../'));
                    $plugin_path_normalized = str_replace('\\', '/', $full_path);
                    if (strpos($plugin_path_normalized, $base_path) === 0) {
                        $plugin_file = substr($plugin_path_normalized, strlen($base_path) + 1);
                    }
                }
            }
            
            // Final check - if still not found, try alternative paths
            if (!$full_path || !file_exists($full_path)) {
                $alt_paths = [
                    __DIR__ . '/../plugins/' . basename($original_plugin_file),
                    __DIR__ . '/../plugins/' . basename(dirname($original_plugin_file)) . '/' . basename($original_plugin_file),
                ];
                
                // Extract folder and file from original path
                $path_parts = explode('/', str_replace('\\', '/', $original_plugin_file));
                if (count($path_parts) >= 2) {
                    $folder = $path_parts[count($path_parts) - 2];
                    $file = $path_parts[count($path_parts) - 1];
                    $alt_paths[] = __DIR__ . '/../plugins/' . $folder . '/' . $file;
                }
                
                $found = false;
                foreach ($alt_paths as $alt_path) {
                    $alt_path = realpath($alt_path);
                    if ($alt_path && file_exists($alt_path)) {
                        $full_path = $alt_path;
                        // Convert to relative path
                        $base_path = str_replace('\\', '/', realpath(__DIR__ . '/../'));
                        $plugin_path_normalized = str_replace('\\', '/', $full_path);
                        if (strpos($plugin_path_normalized, $base_path) === 0) {
                            $plugin_file = substr($plugin_path_normalized, strlen($base_path) + 1);
                        }
                        $found = true;
                        error_log("Plugin activation: Found plugin at alternative path: {$full_path}, normalized to: {$plugin_file}");
                        break;
                    }
                }
                
                if (!$found) {
                    $tried_paths = implode(', ', array_merge([$full_path], $alt_paths));
                    $error_msg = "Plugin file not found. Original: {$original_plugin_file}. Tried: {$tried_paths}";
                    error_log("Plugin activation error: {$error_msg}");
                    throw new Exception($error_msg);
                }
            }
            
            error_log("Plugin activation: Attempting to activate plugin at: {$plugin_file} (full path: {$full_path})");
            
            require_once __DIR__ . '/../config/database.php';
            $db = new Database();
            $conn = $db->getConnection();
            
            if (!$conn) {
                $error_msg = "Database connection failed";
                error_log("Plugin activation error: {$error_msg}");
                throw new Exception($error_msg);
            }
            
            // Create plugins table if it doesn't exist
            try {
                self::createPluginsTable($conn);
                error_log("Plugin activation: Plugins table checked/created successfully");
            } catch (Exception $e) {
                error_log("Plugin activation error: Could not create plugins table: " . $e->getMessage());
                throw new Exception("Database table creation failed: " . $e->getMessage());
            } catch (Error $e) {
                error_log("Plugin activation fatal error: Could not create plugins table: " . $e->getMessage());
                throw new Exception("Database table creation fatal error: " . $e->getMessage());
            }
            
            // Check if plugin exists
            $stmt = $conn->prepare("SELECT * FROM plugins WHERE plugin_file = ?");
            $stmt->execute([$plugin_file]);
            $plugin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Plugin activation: Checking database for plugin_file: {$plugin_file}");
            error_log("Plugin activation: Plugin found in database: " . ($plugin ? 'Yes' : 'No'));
            
            if ($plugin) {
                // Update status
                error_log("Plugin activation: Updating existing plugin record");
                $stmt = $conn->prepare("UPDATE plugins SET status = 'active', activated_at = NOW() WHERE plugin_file = ?");
                $result = $stmt->execute([$plugin_file]);
                $rows_affected = $stmt->rowCount();
                
                if (!$result) {
                    $error_info = $stmt->errorInfo();
                    error_log("Plugin activation error: Failed to update plugin status. SQL Error Code: " . ($error_info[0] ?? 'Unknown'));
                    error_log("Plugin activation error: SQL Error Message: " . ($error_info[2] ?? 'Unknown'));
                    throw new Exception("Failed to update plugin status in database: " . ($error_info[2] ?? 'Unknown SQL error'));
                }
                
                error_log("Plugin activation: Update executed. Rows affected: {$rows_affected}");
                
                if ($rows_affected == 0) {
                    error_log("Plugin activation WARNING: Update executed but no rows were affected");
                    // This might be okay if the plugin was already active, but let's verify
                }
            } else {
                // Insert new plugin
                error_log("Plugin activation: Inserting new plugin record with plugin_file: {$plugin_file}");
                try {
                    $stmt = $conn->prepare("INSERT INTO plugins (plugin_file, status, activated_at) VALUES (?, 'active', NOW())");
                    $result = $stmt->execute([$plugin_file]);
                    $rows_affected = $stmt->rowCount();
                    
                    if (!$result) {
                        $error_info = $stmt->errorInfo();
                        error_log("Plugin activation error: Failed to insert plugin. SQL Error Code: " . ($error_info[0] ?? 'Unknown'));
                        error_log("Plugin activation error: SQL Error Message: " . ($error_info[2] ?? 'Unknown'));
                        throw new Exception("Failed to insert plugin into database: " . ($error_info[2] ?? 'Unknown SQL error'));
                    }
                    
                    error_log("Plugin activation: Insert executed. Rows affected: {$rows_affected}");
                    
                    $inserted_id = $conn->lastInsertId();
                    error_log("Plugin activation: Last insert ID: {$inserted_id}");
                    
                    if (empty($inserted_id)) {
                        error_log("Plugin activation WARNING: Insert executed but no ID returned");
                    }
                    
                    // Verify the insert worked immediately
                    $verify_stmt = $conn->prepare("SELECT * FROM plugins WHERE plugin_file = ?");
                    $verify_stmt->execute([$plugin_file]);
                    $verified = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$verified) {
                        error_log("Plugin activation CRITICAL: Plugin was inserted but cannot be verified in database");
                        error_log("Plugin activation: Tried to verify with plugin_file: {$plugin_file}");
                        
                        // Try to find it with different variations
                        $all_plugins_stmt = $conn->query("SELECT plugin_file, status FROM plugins ORDER BY id DESC LIMIT 5");
                        $all_plugins = $all_plugins_stmt->fetchAll(PDO::FETCH_ASSOC);
                        error_log("Plugin activation: Recent plugins in database: " . json_encode($all_plugins));
                        
                        throw new Exception("Plugin insert verification failed - plugin not found after insert");
                    }
                    error_log("Plugin activation: Verified plugin exists in database after insert. Status: " . ($verified['status'] ?? 'unknown'));
                } catch (PDOException $e) {
                    error_log("Plugin activation PDOException: " . $e->getMessage());
                    error_log("Plugin activation PDOException Code: " . $e->getCode());
                    error_log("Plugin activation PDOException SQL State: " . $e->getCode());
                    throw new Exception("Database error inserting plugin: " . $e->getMessage());
                }
            }
            
            // Fire activation hook before reloading
            try {
                // Get plugin basename (similar to plugin-api.php function)
                if (function_exists('plugin_basename')) {
                    $plugin_basename = plugin_basename($full_path);
                } else {
                    // Fallback: extract plugin basename manually
                    $plugins_dir = str_replace('\\', '/', __DIR__ . '/../plugins');
                    $plugin_path = str_replace('\\', '/', $full_path);
                    $plugin_basename = str_replace($plugins_dir . '/', '', $plugin_path);
                }
                self::doAction('plugin_activated_' . $plugin_basename);
            } catch (Exception $e) {
                error_log("Plugin activation hook error: " . $e->getMessage());
                // Continue even if hook fails
            } catch (Error $e) {
                error_log("Plugin activation hook fatal error: " . $e->getMessage());
                // Continue even if hook fails
            }
            
            // Verify the plugin was saved before proceeding
            $verify_stmt = $conn->prepare("SELECT * FROM plugins WHERE plugin_file = ? AND status = 'active'");
            $verify_stmt->execute([$plugin_file]);
            $verified_plugin = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$verified_plugin) {
                error_log("Plugin activation CRITICAL: Plugin was not found in database after insert/update");
                throw new Exception("Plugin activation failed: Plugin was not saved to database");
            }
            
            error_log("Plugin activation: Final verification passed - plugin is active in database");
            
            // Reload plugins (with error handling)
            try {
                self::$active_plugins = [];
                self::loadActivePlugins();
                // Only activate plugins if we have active plugins loaded
                if (!empty(self::$active_plugins)) {
                    self::activatePlugins();
                }
            } catch (Exception $e) {
                error_log("Plugin reload error after activation: " . $e->getMessage());
                // Still return true if database update succeeded
            }
            
            return true;
        } catch (PDOException $e) {
            $error_msg = "Database error: " . $e->getMessage();
            error_log("Plugin activation database error: " . $e->getMessage());
            error_log("PDOException Code: " . $e->getCode());
            throw new Exception($error_msg, 0, $e);
        } catch (Exception $e) {
            error_log("Plugin activation error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            // Re-throw exception so caller can see the actual error
            throw $e;
        } catch (Error $e) {
            $error_msg = "Fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
            error_log("Plugin activation fatal error: " . $e->getMessage());
            error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
            throw new Exception($error_msg, 0, $e);
        }
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

