<?php
/**
 * Plugin Store
 * Browse and install plugins from License Server
 */

// Start output buffering to catch any errors
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly, we'll handle them
ini_set('log_errors', 1);

// Set custom error handler to catch fatal errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Plugin Store Error [$errno]: $errstr in $errfile:$errline");
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
$page_title = 'Plugin Store';
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
    error_log("Plugin Store - auth-check.php error: " . $e->getMessage());
} catch (Error $e) {
    $init_error = 'Authentication check fatal error: ' . $e->getMessage();
    error_log("Plugin Store - auth-check.php fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}

try {
    if (!file_exists(__DIR__ . '/../config/database.php')) {
        throw new Exception('database.php file not found');
    }
    require_once __DIR__ . '/../config/database.php';
} catch (Exception $e) {
    $init_error = ($init_error ? $init_error . ' | ' : '') . 'Database config failed: ' . $e->getMessage();
    error_log("Plugin Store - database.php error: " . $e->getMessage());
} catch (Error $e) {
    $init_error = ($init_error ? $init_error . ' | ' : '') . 'Database config fatal error: ' . $e->getMessage();
    error_log("Plugin Store - database.php fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
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
        
        // Validate and correct plugin slug - prevent common mistakes
        $plugin_slug = trim($plugin_slug);
        if (preg_match('/^3-agger$/i', $plugin_slug)) {
            // Common typo - should be mp3-tagger
            error_log("Plugin installation: Detected incorrect plugin slug '3-agger', correcting to 'mp3-tagger'");
            $plugin_slug = 'mp3-tagger';
        }
        
        // Additional validation: plugin slug should be alphanumeric with hyphens/underscores
        if (!preg_match('/^[a-z0-9_-]+$/i', $plugin_slug)) {
            throw new Exception('Invalid plugin slug format. Plugin slug must contain only letters, numbers, hyphens, and underscores.');
        }
        
        // Verify plugin exists on license server - first check available plugins list
        $plugin_found_in_list = false;
        $actual_plugin_slug = $plugin_slug;
        
        // Ensure $available_plugins is an array
        if (!is_array($available_plugins)) {
            $available_plugins = [];
        }
        
        foreach ($available_plugins as $available_plugin) {
            $available_slug = $available_plugin['plugin_slug'] ?? '';
            // Check exact match or case-insensitive match
            if (strtolower($available_slug) === strtolower($plugin_slug)) {
                $plugin_found_in_list = true;
                $actual_plugin_slug = $available_slug; // Use the exact slug from server
                error_log("Plugin installation: Found plugin in available list - slug: {$actual_plugin_slug}");
                break;
            }
        }
        
        // If not found in list, try the info API endpoint
        if (!$plugin_found_in_list) {
            error_log("Plugin installation: Plugin not in available list, checking info API...");
            $verify_url = rtrim($license_server_url, '/') . '/api/plugin-store.php?action=info&plugin_slug=' . urlencode($plugin_slug);
            if (!empty($license_key)) {
                $verify_url .= '&license_key=' . urlencode($license_key);
            }
            
            $verify_response = false;
            if (function_exists('curl_init')) {
                $ch = curl_init($verify_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                $verify_response = curl_exec($ch);
                $verify_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($verify_http_code === 404) {
                    // List available plugin slugs for debugging
                    $available_slugs = [];
                    if (is_array($available_plugins) && !empty($available_plugins)) {
                        $available_slugs = array_map(function($p) { return $p['plugin_slug'] ?? ''; }, $available_plugins);
                        $available_slugs = array_filter($available_slugs);
                    }
                    $available_slugs_str = !empty($available_slugs) ? implode(', ', $available_slugs) : 'none';
                    throw new Exception("Plugin '{$plugin_slug}' not found on license server. Available plugins: {$available_slugs_str}. Please ensure the plugin has been uploaded to the license server at: {$license_server_url}");
                } elseif ($verify_http_code !== 200) {
                    error_log("Plugin installation: Plugin verification returned HTTP {$verify_http_code}");
                    // Continue anyway, might still be able to download
                }
            } else {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 10,
                        'follow_location' => true,
                        'ignore_errors' => true
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]);
                $verify_response = @file_get_contents($verify_url, false, $context);
            }
            
            if ($verify_response) {
                $verify_data = json_decode($verify_response, true);
                if ($verify_data && isset($verify_data['success']) && $verify_data['success'] && isset($verify_data['plugin'])) {
                    $plugin_found_in_list = true;
                    $actual_plugin_slug = $verify_data['plugin']['plugin_slug'] ?? $plugin_slug;
                    error_log("Plugin installation: Verified plugin exists via info API - slug: {$actual_plugin_slug}");
                } elseif ($verify_data && isset($verify_data['error'])) {
                    throw new Exception("License server error: " . $verify_data['error']);
                }
            }
        }
        
        // Use the actual plugin slug from server (might be different case)
        if ($actual_plugin_slug !== $plugin_slug) {
            error_log("Plugin installation: Using server plugin slug: {$actual_plugin_slug} (requested: {$plugin_slug})");
            $plugin_slug = $actual_plugin_slug;
        }
        
        // Download plugin from license server
        $download_url = rtrim($license_server_url, '/') . '/api/plugin-store.php?action=download&plugin_slug=' . urlencode($plugin_slug);
        if (!empty($license_key)) {
            $download_url .= '&license_key=' . urlencode($license_key);
        }
        
        error_log("Plugin installation: Attempting to download from: {$download_url}");
        
        // Use cURL for better error handling
        $plugin_zip = false;
        $download_error = '';
        $http_code = 0;
        
        if (function_exists('curl_init')) {
            $ch = curl_init($download_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 second timeout
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 second connection timeout
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Allow self-signed certificates
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Music Platform Plugin Installer/1.0');
            
            $plugin_zip = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                $download_error = "cURL error: {$curl_error}";
                error_log("Plugin installation: cURL error - {$curl_error}");
            } elseif ($http_code !== 200) {
                $download_error = "HTTP error: {$http_code}";
                error_log("Plugin installation: HTTP error - {$http_code}");
                // Try to parse error message from response
                if ($plugin_zip) {
                    $error_data = json_decode($plugin_zip, true);
                    if ($error_data && isset($error_data['error'])) {
                        $download_error .= " - " . $error_data['error'];
                    } else {
                        // Show first 200 characters of response
                        $preview = substr($plugin_zip, 0, 200);
                        $download_error .= " - Response: " . htmlspecialchars($preview);
                    }
                }
            }
        } else {
            // Fallback to file_get_contents if cURL is not available
            $context = stream_context_create([
                'http' => [
                    'timeout' => 60,
                    'follow_location' => true,
                    'user_agent' => 'Music Platform Plugin Installer/1.0',
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            $plugin_zip = @file_get_contents($download_url, false, $context);
            
            if ($plugin_zip === false) {
                $last_error = error_get_last();
                $download_error = "file_get_contents failed";
                if ($last_error && strpos($last_error['message'], 'file_get_contents') !== false) {
                    $download_error .= ": " . $last_error['message'];
                }
                error_log("Plugin installation: file_get_contents failed - " . ($last_error['message'] ?? 'Unknown error'));
            } else {
                // Check HTTP response code from headers
                if (isset($http_response_header)) {
                    foreach ($http_response_header as $header) {
                        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                            $http_code = (int)$matches[1];
                            if ($http_code !== 200) {
                                $download_error = "HTTP error: {$http_code}";
                                error_log("Plugin installation: HTTP error from file_get_contents - {$http_code}");
                            }
                            break;
                        }
                    }
                }
            }
        }
        
        // Validate that we got a ZIP file
        if ($plugin_zip === false || !empty($download_error)) {
            $error_msg = 'Failed to download plugin from license server.';
            if (!empty($download_error)) {
                $error_msg .= ' ' . $download_error;
            }
            if ($http_code > 0) {
                $error_msg .= " (HTTP {$http_code})";
            }
            $error_msg .= " URL: {$download_url}";
            throw new Exception($error_msg);
        }
        
        // Check if response is actually a ZIP file (starts with PK header)
        if (substr($plugin_zip, 0, 2) !== 'PK') {
            // Might be a JSON error response
            $error_data = json_decode($plugin_zip, true);
            if ($error_data && isset($error_data['error'])) {
                throw new Exception('License server error: ' . $error_data['error']);
            } elseif ($error_data && isset($error_data['message'])) {
                throw new Exception('License server error: ' . $error_data['message']);
            } else {
                // Show first 500 characters for debugging
                $preview = substr($plugin_zip, 0, 500);
                error_log("Plugin installation: Response is not a ZIP file. Preview: " . $preview);
                throw new Exception('Downloaded file is not a valid ZIP file. Server may have returned an error. Check error logs for details.');
            }
        }
        
        error_log("Plugin installation: Successfully downloaded plugin ZIP file (" . strlen($plugin_zip) . " bytes)");
        
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
            // Recursively remove existing directory
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($extract_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                @$todo($fileinfo->getRealPath());
            }
            @rmdir($extract_dir);
        }
        // Create directory if it doesn't exist (mkdir with recursive flag won't error if it exists)
        if (!is_dir($extract_dir)) {
            mkdir($extract_dir, 0755, true);
        }
        
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
            
            // If main plugin file not found, search for the main plugin file
            if (!file_exists($plugin_file)) {
                // First, try to find the main plugin file (not in admin/ or includes/ folders)
                $search_paths = [
                    $extract_dir . $plugin_slug . '/' . $plugin_slug . '.php', // plugins/plugin-slug/plugin-slug.php
                    $extract_dir . $plugin_slug . '.php', // plugins/plugin-slug.php (if files extracted to root)
                ];
                
                foreach ($search_paths as $search_path) {
                    if (file_exists($search_path)) {
                        $plugin_file = $search_path;
                        break;
                    }
                }
                
                // If still not found, search for PHP files but STRICTLY exclude admin/ and includes/ folders
                if (!file_exists($plugin_file)) {
                    // Use RecursiveIteratorIterator to get all PHP files
                    $all_php_files = [];
                    $search_dirs = [];
                    
                    if (is_dir($extract_dir . $plugin_slug)) {
                        $search_dirs[] = $extract_dir . $plugin_slug;
                    }
                    $search_dirs[] = $extract_dir;
                    
                    foreach ($search_dirs as $search_dir) {
                        if (!is_dir($search_dir)) continue;
                        
                        try {
                            $iterator = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator($search_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                                RecursiveIteratorIterator::SELF_FIRST
                            );
                            
                            foreach ($iterator as $file) {
                                if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                                    $all_php_files[] = $file->getPathname();
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Plugin installation: Error scanning directory {$search_dir}: " . $e->getMessage());
                        }
                    }
                    
                    // Also check root level
                    $root_files = glob($extract_dir . '*.php');
                    if ($root_files) {
                        $all_php_files = array_merge($all_php_files, $root_files);
                    }
                    
                    // Filter out admin/, includes/, assets/ files completely - use normalized paths
                    $valid_files = [];
                    $extract_dir_normalized = str_replace('\\', '/', rtrim($extract_dir, '/\\'));
                    
                    foreach ($all_php_files as $php_file) {
                        $php_file_normalized = str_replace('\\', '/', $php_file);
                        $relative = str_replace($extract_dir_normalized . '/', '', $php_file_normalized);
                        
                        // Skip any file in admin/, includes/, assets/ directories (case-insensitive, handle both / and \)
                        if (preg_match('#(^|[/\\\\])(admin|includes|assets)([/\\\\]|$)#i', $relative)) {
                            error_log("Plugin installation: SKIPPING excluded file: {$php_file} (relative: {$relative})");
                            continue;
                        }
                        
                        // Only consider files directly in plugin root or plugin-slug root (one level deep max)
                        $parts = explode('/', $relative);
                        if (count($parts) === 1 || (count($parts) === 2 && $parts[0] === $plugin_slug)) {
                            // Check if file has Plugin Name header (preferred)
                            $content = @file_get_contents($php_file);
                            $has_header = $content && (
                                stripos($content, 'Plugin Name:') !== false || 
                                stripos($content, '* Plugin Name') !== false ||
                                stripos($content, 'Plugin Name') !== false
                            );
                            
                            if ($has_header) {
                                // Prioritize files named after plugin slug
                                if (basename($php_file, '.php') === $plugin_slug) {
                                    array_unshift($valid_files, $php_file);
                                } else {
                                    $valid_files[] = $php_file;
                                }
                            } else {
                                // Only add files without header if they're named after plugin slug
                                if (basename($php_file, '.php') === $plugin_slug) {
                                    $valid_files[] = $php_file;
                                }
                            }
                        }
                    }
                    
                    if (!empty($valid_files)) {
                        $plugin_file = $valid_files[0];
                        error_log("Plugin installation: Using main plugin file: {$plugin_file}");
                    } else {
                        error_log("Plugin installation: No valid plugin file found. Searched " . count($all_php_files) . " PHP files.");
                    }
                }
            }
            
            // Final check - make sure we have a valid plugin file
            if (!file_exists($plugin_file)) {
                throw new Exception('Main plugin file not found. Expected: ' . $plugin_slug . '.php in ' . $extract_dir);
            }
            
            // CRITICAL VALIDATION: Check the actual file path (before normalization) to ensure it's not in admin/includes/assets
            $plugin_file_normalized_check = str_replace('\\', '/', $plugin_file);
            $extract_dir_normalized_check = str_replace('\\', '/', rtrim($extract_dir, '/\\'));
            $relative_check = str_replace($extract_dir_normalized_check . '/', '', $plugin_file_normalized_check);
            
            // Reject if file is in admin/, includes/, or assets/ directories
            if (preg_match('#(^|[/\\\\])(admin|includes|assets)([/\\\\]|$)#i', $relative_check)) {
                error_log("Plugin installation CRITICAL ERROR: Selected file is in excluded directory!");
                error_log("  - File: {$plugin_file}");
                error_log("  - Normalized: {$plugin_file_normalized_check}");
                error_log("  - Relative: {$relative_check}");
                throw new Exception('CRITICAL: The selected plugin file is in an excluded directory (admin/includes/assets). This should not happen. File: ' . basename($plugin_file) . ' in ' . dirname($relative_check));
            }
            
            // Also check the actual file content to ensure it has Plugin Name header
            $file_check_content = @file_get_contents($plugin_file);
            if (!$file_check_content || (
                stripos($file_check_content, 'Plugin Name:') === false && 
                stripos($file_check_content, '* Plugin Name') === false &&
                stripos($file_check_content, 'Plugin Name') === false
            )) {
                error_log("Plugin installation WARNING: Selected file does not have Plugin Name header: {$plugin_file}");
                // Don't throw here, let the later validation handle it, but log it
            }
            
            // Verify this is the main plugin file (check for plugin header) - REQUIRED
            $file_content = @file_get_contents($plugin_file);
            $has_plugin_header = $file_content && (
                stripos($file_content, 'Plugin Name:') !== false || 
                stripos($file_content, '* Plugin Name') !== false ||
                stripos($file_content, 'Plugin Name') !== false
            );
            
            if (!$has_plugin_header) {
                error_log("Plugin installation WARNING: File {$plugin_file} does not have Plugin Name header. Searching for main file...");
                
                // Try to find the actual main plugin file with header
                $search_dirs = [];
                if (is_dir($extract_dir . $plugin_slug)) {
                    $search_dirs[] = $extract_dir . $plugin_slug;
                }
                $search_dirs[] = $extract_dir;
                
                $found_main_file = false;
                foreach ($search_dirs as $search_dir) {
                    if (!is_dir($search_dir)) continue;
                    
                    try {
                        $iterator = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($search_dir, RecursiveDirectoryIterator::SKIP_DOTS)
                        );
                        
                        foreach ($iterator as $file) {
                            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                                $test_file = $file->getPathname();
                                $test_normalized = str_replace('\\', '/', $test_file);
                                $test_relative = str_replace(str_replace('\\', '/', $extract_dir), '', $test_normalized);
                                
                                // Skip admin/includes/assets
                                if (preg_match('#(^|[/\\\\])(admin|includes|assets)([/\\\\]|$)#i', $test_relative)) {
                                    continue;
                                }
                                
                                $test_content = @file_get_contents($test_file);
                                if ($test_content && (
                                    stripos($test_content, 'Plugin Name:') !== false || 
                                    stripos($test_content, '* Plugin Name') !== false ||
                                    stripos($test_content, 'Plugin Name') !== false
                                )) {
                                    $plugin_file = $test_file;
                                    error_log("Plugin installation: Found main plugin file with header: {$plugin_file}");
                                    $found_main_file = true;
                                    break 2;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Plugin installation: Error searching for main file in {$search_dir}: " . $e->getMessage());
                    }
                }
                
                if (!$found_main_file) {
                    throw new Exception('Main plugin file with "Plugin Name:" header not found in ' . $extract_dir . '. Please ensure your plugin has a main file with a Plugin Name header.');
                }
            }
            
            if (file_exists($plugin_file)) {
                if (!class_exists('PluginLoader')) {
                    throw new Exception('Plugin system not loaded. Cannot activate plugin.');
                }
                
                // Normalize plugin file path (use relative path from plugins directory)
                $plugins_base = realpath(__DIR__ . '/../plugins/');
                $plugin_file_normalized = $plugin_file;
                
                // Normalize path separators first
                $plugin_file_normalized = str_replace('\\', '/', $plugin_file_normalized);
                $plugins_base_normalized = str_replace('\\', '/', $plugins_base);
                
                // Convert to relative path if absolute
                if (strpos($plugin_file_normalized, $plugins_base_normalized) === 0) {
                    // File is in plugins directory
                    $plugin_file_normalized = str_replace($plugins_base_normalized . '/', '', $plugin_file_normalized);
                    $plugin_file_normalized = 'plugins/' . $plugin_file_normalized;
                } else {
                    // File might be in extract_dir, need to move it to plugins directory first
                    $extract_dir_normalized = str_replace('\\', '/', rtrim($extract_dir, '/\\'));
                    if (strpos($plugin_file_normalized, $extract_dir_normalized) === 0) {
                        // Extract relative path from extract_dir
                        $relative_from_extract = str_replace($extract_dir_normalized . '/', '', $plugin_file_normalized);
                        
                        // Determine final plugin path
                        $final_plugin_path = $plugins_base . '/' . $relative_from_extract;
                        $final_plugin_dir = dirname($final_plugin_path);
                        
                        // Ensure plugin directory exists
                        if (!is_dir($final_plugin_dir)) {
                            @mkdir($final_plugin_dir, 0755, true);
                        }
                        
                        // Move file to plugins directory if it's not already there
                        if ($plugin_file_normalized !== str_replace('\\', '/', $final_plugin_path)) {
                            if (file_exists($plugin_file)) {
                                if (@copy($plugin_file, $final_plugin_path)) {
                                    error_log("Plugin installation: Copied plugin file to: {$final_plugin_path}");
                                    $plugin_file = $final_plugin_path;
                                    $plugin_file_normalized = str_replace('\\', '/', $final_plugin_path);
                                }
                            }
                        }
                        
                        // Normalize to relative path from plugins base
                        $plugin_file_normalized = str_replace($plugins_base_normalized . '/', '', $plugin_file_normalized);
                        $plugin_file_normalized = 'plugins/' . $plugin_file_normalized;
                    } else {
                        // Try to extract plugin slug and construct path
                        if (preg_match('#([^/\\\\]+)[/\\\\]([^/\\\\]+\.php)$#', $plugin_file_normalized, $matches)) {
                            $possible_slug = $matches[1];
                            $filename = $matches[2];
                            $plugin_file_normalized = 'plugins/' . $possible_slug . '/' . $filename;
                        } else {
                            // Last resort: use basename
                            $plugin_file_normalized = 'plugins/' . $plugin_slug . '/' . basename($plugin_file_normalized);
                        }
                    }
                }
                
                // Final validation: ensure normalized path doesn't contain admin/includes/assets (multiple patterns)
                $validation_failed = false;
                $validation_patterns = [
                    '#plugins/[^/]+/(admin|includes|assets)/#i',  // plugins/slug/admin/
                    '#plugins/[^/]+/(admin|includes|assets)\\\\#i',  // plugins/slug/admin\ (backslash)
                    '#/(admin|includes|assets)/#i',  // Any /admin/ in path
                    '#\\\\(admin|includes|assets)\\\\#i',  // Any \admin\ in path
                ];
                
                foreach ($validation_patterns as $pattern) {
                    if (preg_match($pattern, $plugin_file_normalized)) {
                        error_log("Plugin installation CRITICAL ERROR: Detected excluded directory in normalized path!");
                        error_log("  - Pattern: {$pattern}");
                        error_log("  - Path: {$plugin_file_normalized}");
                        error_log("  - Original file: {$plugin_file}");
                        $validation_failed = true;
                        break;
                    }
                }
                
                if ($validation_failed) {
                    throw new Exception('CRITICAL: Invalid plugin file path detected after normalization. The main plugin file cannot be in admin/, includes/, or assets/ directories. Path: ' . $plugin_file_normalized);
                }
                
                error_log("Plugin installation: Normalized plugin file path: {$plugin_file_normalized}");
                
                // Activate the plugin (this saves it to database)
                error_log("Attempting to activate plugin: {$plugin_file_normalized}");
                $activation_result = PluginLoader::activatePlugin($plugin_file_normalized);
                
                if ($activation_result === true) {
                    // Force reload plugins to detect the newly installed one
                    try {
                        PluginLoader::init(true); // Force reload
                    } catch (Exception $e) {
                        error_log("Error reloading plugins after installation: " . $e->getMessage());
                        // Continue anyway, plugin is already activated
                    }
                    $success = 'Plugin "' . htmlspecialchars($plugin_name) . '" installed and activated successfully!';
                } else {
                    // Get more detailed error information
                    $error_details = error_get_last();
                    $error_msg = 'Failed to activate plugin.';
                    
                    // Check if plugin was at least saved to database
                    try {
                        $db = new Database();
                        $conn = $db->getConnection();
                        if ($conn) {
                            // Try multiple variations of the plugin file path
                            $paths_to_check = [
                                $plugin_file_normalized,
                                str_replace('plugins/', '', $plugin_file_normalized),
                                basename(dirname($plugin_file_normalized)) . '/' . basename($plugin_file_normalized),
                                'plugins/' . basename(dirname($plugin_file_normalized)) . '/' . basename($plugin_file_normalized)
                            ];
                            
                            $saved_plugin = null;
                            foreach ($paths_to_check as $check_path) {
                                $check_stmt = $conn->prepare("SELECT * FROM plugins WHERE plugin_file = ?");
                                $check_stmt->execute([$check_path]);
                                $saved_plugin = $check_stmt->fetch(PDO::FETCH_ASSOC);
                                if ($saved_plugin) {
                                    error_log("Found plugin in database with path: {$check_path}");
                                    break;
                                }
                            }
                            
                            if ($saved_plugin) {
                                $error_msg .= ' Plugin was saved to database but activation returned false.';
                                $error_msg .= ' Plugin status: ' . ($saved_plugin['status'] ?? 'unknown');
                                $error_msg .= ' Plugin file in DB: ' . ($saved_plugin['plugin_file'] ?? 'unknown');
                            } else {
                                $error_msg .= ' Plugin was not saved to database.';
                                $error_msg .= ' Checked paths: ' . implode(', ', $paths_to_check);
                                
                                // Check if plugins table exists
                                $table_check = $conn->query("SHOW TABLES LIKE 'plugins'");
                                if ($table_check->rowCount() == 0) {
                                    $error_msg .= ' (Plugins table does not exist)';
                                } else {
                                    $error_msg .= ' (Plugins table exists but plugin not found)';
                                }
                            }
                        } else {
                            $error_msg .= ' Database connection failed during verification.';
                        }
                    } catch (Exception $e) {
                        $error_msg .= ' Could not verify database status: ' . $e->getMessage();
                    }
                    
                    if ($error_details) {
                        $error_msg .= ' Last error: ' . $error_details['message'];
                    }
                    
                    error_log("Plugin activation failed for: {$plugin_file_normalized}");
                    error_log("Activation result: " . var_export($activation_result, true));
                    throw new Exception($error_msg . ' Please try activating it manually from the Plugins page.');
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
$license_server_status = 'unknown';
$license_server_error = '';

if (!empty($license_server_url)) {
    try {
        $store_url = rtrim($license_server_url, '/') . '/api/plugin-store.php?action=list';
        if (!empty($license_key)) {
            $store_url .= '&license_key=' . urlencode($license_key);
        }
        
        error_log("Plugin Store: Fetching plugins from: {$store_url}");
        
        // Use cURL for better error handling
        if (function_exists('curl_init')) {
            $ch = curl_init($store_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Music Platform Plugin Store/1.0');
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                $license_server_status = 'error';
                $license_server_error = "Connection error: {$curl_error}";
                error_log("Plugin Store: cURL error - {$curl_error}");
            } elseif ($http_code !== 200) {
                $license_server_status = 'error';
                $license_server_error = "HTTP error: {$http_code}";
                error_log("Plugin Store: HTTP error - {$http_code}");
                if ($response) {
                    $error_data = json_decode($response, true);
                    if ($error_data && isset($error_data['error'])) {
                        $license_server_error .= " - " . $error_data['error'];
                    }
                }
            } else {
                $license_server_status = 'connected';
                if ($response) {
                    $data = json_decode($response, true);
                    if ($data && $data['success'] && isset($data['plugins'])) {
                        $available_plugins = $data['plugins'];
                        error_log("Plugin Store: Successfully fetched " . count($available_plugins) . " plugins");
                    } else {
                        $license_server_status = 'warning';
                        $license_server_error = "Invalid response format from license server";
                        error_log("Plugin Store: Invalid response format");
                    }
                }
            }
        } else {
            // Fallback to file_get_contents
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'follow_location' => true,
                    'ignore_errors' => true,
                    'user_agent' => 'Music Platform Plugin Store/1.0'
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            $response = @file_get_contents($store_url, false, $context);
            if ($response !== false) {
                $license_server_status = 'connected';
                $data = json_decode($response, true);
                if ($data && $data['success'] && isset($data['plugins'])) {
                    $available_plugins = $data['plugins'];
                }
            } else {
                $license_server_status = 'error';
                $license_server_error = "Failed to connect to license server";
                $last_error = error_get_last();
                if ($last_error) {
                    $license_server_error .= ": " . $last_error['message'];
                }
            }
        }
    } catch (Exception $e) {
        $license_server_status = 'error';
        $license_server_error = $e->getMessage();
        error_log("Plugin Store: Exception - " . $e->getMessage());
    }
} else {
    $license_server_status = 'not_configured';
    $license_server_error = "License server URL not configured";
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
        
        <?php if (!empty($license_server_url)): ?>
        <div style="margin-top: 20px; padding: 15px; background: #f9fafb; border-radius: 5px;">
            <strong>Connection Status:</strong>
            <?php if ($license_server_status === 'connected'): ?>
                <span style="color: #10b981; font-weight: bold;">
                    <i class="fas fa-check-circle"></i> Connected
                </span>
                <br><small style="color: #6b7280;">Found <?php echo count($available_plugins); ?> plugin(s) available.</small>
            <?php elseif ($license_server_status === 'warning'): ?>
                <span style="color: #f59e0b; font-weight: bold;">
                    <i class="fas fa-exclamation-triangle"></i> Warning
                </span>
                <br><small style="color: #6b7280;"><?php echo htmlspecialchars($license_server_error); ?></small>
            <?php elseif ($license_server_status === 'error'): ?>
                <span style="color: #ef4444; font-weight: bold;">
                    <i class="fas fa-times-circle"></i> Connection Error
                </span>
                <br><small style="color: #6b7280;"><?php echo htmlspecialchars($license_server_error); ?></small>
                <br><small style="color: #6b7280;">Please check:</small>
                <ul style="margin: 10px 0 0 20px; color: #6b7280;">
                    <li>The license server URL is correct: <code><?php echo htmlspecialchars($license_server_url); ?></code></li>
                    <li>The license server is accessible from this server</li>
                    <li>The API endpoint exists: <code>/api/plugin-store.php</code></li>
                </ul>
            <?php elseif ($license_server_status === 'not_configured'): ?>
                <span style="color: #6b7280; font-weight: bold;">
                    <i class="fas fa-info-circle"></i> Not Configured
                </span>
            <?php else: ?>
                <span style="color: #6b7280; font-weight: bold;">
                    <i class="fas fa-question-circle"></i> Unknown
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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

