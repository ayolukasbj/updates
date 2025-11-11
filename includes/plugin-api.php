<?php
/**
 * Plugin API Functions
 * WordPress-like helper functions for plugins
 */

/**
 * Add an action hook
 */
function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1) {
    PluginLoader::addAction($hook_name, $callback, $priority, $accepted_args);
}

/**
 * Execute an action hook
 */
function do_action($hook_name, ...$args) {
    PluginLoader::doAction($hook_name, ...$args);
}

/**
 * Add a filter hook
 */
function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1) {
    PluginLoader::addFilter($hook_name, $callback, $priority, $accepted_args);
}

/**
 * Apply a filter hook
 */
function apply_filters($hook_name, $value, ...$args) {
    return PluginLoader::applyFilters($hook_name, $value, ...$args);
}

/**
 * Get plugin directory URL
 */
function plugin_dir_url($file) {
    $plugin_dir = dirname($file);
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $base_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(__DIR__));
    return $base_url . $base_path . '/plugins/' . basename($plugin_dir) . '/';
}

/**
 * Get plugin directory path
 */
function plugin_dir_path($file) {
    return dirname($file) . '/';
}

/**
 * Get plugin base name
 */
function plugin_basename($file) {
    $plugin_dir = str_replace('\\', '/', dirname($file));
    $plugins_dir = str_replace('\\', '/', __DIR__ . '/../plugins');
    return str_replace($plugins_dir . '/', '', $file);
}

/**
 * Register activation hook
 */
function register_activation_hook($file, $callback) {
    add_action('plugin_activated_' . plugin_basename($file), $callback);
}

/**
 * Register deactivation hook
 */
function register_deactivation_hook($file, $callback) {
    add_action('plugin_deactivated_' . plugin_basename($file), $callback);
}

/**
 * Get site URL
 */
function site_url($path = '') {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
    $host = $_SERVER['HTTP_HOST'];
    $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
    return $protocol . '://' . $host . $base_path . ltrim($path, '/');
}

/**
 * Get admin URL
 */
function admin_url($path = '') {
    return site_url('admin/' . ltrim($path, '/'));
}

/**
 * Get database connection
 */
function get_db_connection() {
    require_once __DIR__ . '/../config/database.php';
    $db = new Database();
    return $db->getConnection();
}

/**
 * Get option value
 */
function get_option($option_name, $default = false) {
    try {
        $conn = get_db_connection();
        if ($conn) {
            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$option_name]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['setting_value'] : $default;
        }
    } catch (Exception $e) {
        error_log("Error getting option: " . $e->getMessage());
    }
    return $default;
}

/**
 * Update option value
 */
function update_option($option_name, $option_value) {
    try {
        $conn = get_db_connection();
        if ($conn) {
            $stmt = $conn->prepare("SELECT setting_key FROM settings WHERE setting_key = ?");
            $stmt->execute([$option_name]);
            
            if ($stmt->rowCount() > 0) {
                $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$option_value, $option_name]);
            } else {
                $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$option_name, $option_value]);
            }
            return true;
        }
    } catch (Exception $e) {
        error_log("Error updating option: " . $e->getMessage());
    }
    return false;
}

/**
 * Delete option
 */
function delete_option($option_name) {
    try {
        $conn = get_db_connection();
        if ($conn) {
            $stmt = $conn->prepare("DELETE FROM settings WHERE setting_key = ?");
            $stmt->execute([$option_name]);
            return true;
        }
    } catch (Exception $e) {
        error_log("Error deleting option: " . $e->getMessage());
    }
    return false;
}

/**
 * Add admin menu page
 */
function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null) {
    // Register menu item directly
    PluginLoader::registerAdminMenu([
        'page_title' => $page_title,
        'menu_title' => $menu_title,
        'capability' => $capability,
        'menu_slug' => $menu_slug,
        'function' => $function,
        'icon_url' => $icon_url,
        'position' => $position
    ]);
}

/**
 * Add admin submenu page
 */
function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '') {
    // Register submenu item directly
    PluginLoader::registerAdminSubmenu([
        'parent_slug' => $parent_slug,
        'page_title' => $page_title,
        'menu_title' => $menu_title,
        'capability' => $capability,
        'menu_slug' => $menu_slug,
        'function' => $function
    ]);
}

/**
 * Add settings field
 */
function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = []) {
    add_action('admin_init', function() use ($id, $title, $callback, $page, $section, $args) {
        do_action('add_settings_field', [
            'id' => $id,
            'title' => $title,
            'callback' => $callback,
            'page' => $page,
            'section' => $section,
            'args' => $args
        ]);
    });
}

/**
 * Register settings
 */
function register_setting($option_group, $option_name, $args = []) {
    add_action('admin_init', function() use ($option_group, $option_name, $args) {
        do_action('register_setting', [
            'option_group' => $option_group,
            'option_name' => $option_name,
            'args' => $args
        ]);
    });
}

/**
 * Enqueue script
 */
function wp_enqueue_script($handle, $src = '', $deps = [], $version = false, $in_footer = false) {
    add_action('wp_enqueue_scripts', function() use ($handle, $src, $deps, $version, $in_footer) {
        do_action('enqueue_script', [
            'handle' => $handle,
            'src' => $src,
            'deps' => $deps,
            'version' => $version,
            'in_footer' => $in_footer
        ]);
    });
}

/**
 * Enqueue style
 */
function wp_enqueue_style($handle, $src = '', $deps = [], $version = false, $media = 'all') {
    add_action('wp_enqueue_scripts', function() use ($handle, $src, $deps, $version, $media) {
        do_action('enqueue_style', [
            'handle' => $handle,
            'src' => $src,
            'deps' => $deps,
            'version' => $version,
            'media' => $media
        ]);
    });
}

/**
 * Sanitize text field
 */
function sanitize_text_field($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

/**
 * Escape HTML
 */
function esc_html($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape attribute
 */
function esc_attr($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape URL
 */
function esc_url($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}

/**
 * Checked helper
 */
function checked($value, $compare) {
    return $value === $compare ? 'checked' : '';
}

