# Plugin System Guide

## Overview

The Music Platform now includes a WordPress-like plugin system that allows third-party developers to extend functionality without modifying core files.

## Features

- **Action Hooks**: Execute code at specific points
- **Filter Hooks**: Modify data before it's used
- **Plugin Management**: Activate/deactivate plugins from admin panel
- **Plugin API**: WordPress-like functions for easy development
- **Database Integration**: Store plugin options and settings

## Installation

The plugin system is automatically loaded when you include the header. No additional setup required!

## Creating a Plugin

### Step 1: Create Plugin Folder

Create a folder in the `plugins/` directory with your plugin name:

```
plugins/
  └── my-awesome-plugin/
      └── my-awesome-plugin.php
```

### Step 2: Add Plugin Header

Add the plugin header at the top of your PHP file:

```php
<?php
/**
 * Plugin Name: My Awesome Plugin
 * Plugin URI: https://example.com/my-plugin
 * Description: This plugin does amazing things
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: my-plugin
 * Requires PHP: 7.4
 */
```

### Step 3: Write Your Plugin Code

```php
// Prevent direct access
if (!defined('ABSPATH') && !function_exists('add_action')) {
    exit;
}

// Add action hook
add_action('init', function() {
    // Your code here
    echo 'Plugin loaded!';
});

// Add filter hook
add_filter('song_title', function($title) {
    return strtoupper($title);
}, 10, 1);
```

## Available Hooks

### Action Hooks

Actions are executed at specific points in the code. Use `do_action()` to create new hooks or `add_action()` to hook into existing ones.

**Common Action Hooks:**
- `init` - After plugin system initialization
- `homepage_content_before` - Before homepage content
- `homepage_content_after` - After homepage content
- `song_uploaded` - After song is uploaded
- `user_registered` - After user registration
- `admin_menu` - When building admin menu

**Example:**
```php
add_action('song_uploaded', function($song_id, $song_data) {
    // Send notification email
    mail('admin@example.com', 'New Song Uploaded', 'Song ID: ' . $song_id);
}, 10, 2);
```

### Filter Hooks

Filters modify data before it's used. Use `apply_filters()` to create new filters or `add_filter()` to modify existing ones.

**Common Filter Hooks:**
- `song_title` - Modify song title
- `song_artist` - Modify artist name
- `page_title` - Modify page title
- `site_url` - Modify site URL

**Example:**
```php
add_filter('song_title', function($title) {
    // Add prefix to all titles
    return '[NEW] ' . $title;
}, 10, 1);
```

## Plugin API Functions

### Action Functions

```php
// Add action
add_action($hook_name, $callback, $priority = 10, $accepted_args = 1);

// Execute action
do_action($hook_name, ...$args);
```

### Filter Functions

```php
// Add filter
add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1);

// Apply filter
apply_filters($hook_name, $value, ...$args);
```

### Option Functions

```php
// Get option
$value = get_option('option_name', $default);

// Update option
update_option('option_name', 'value');

// Delete option
delete_option('option_name');
```

### URL Functions

```php
// Get site URL
$url = site_url('path/to/page');

// Get admin URL
$admin_url = admin_url('settings.php');
```

### Plugin Functions

```php
// Get plugin directory URL
$plugin_url = plugin_dir_url(__FILE__);

// Get plugin directory path
$plugin_path = plugin_dir_path(__FILE__);

// Get plugin basename
$basename = plugin_basename(__FILE__);
```

### Activation/Deactivation Hooks

```php
// Register activation hook
register_activation_hook(__FILE__, function() {
    // Code to run when plugin is activated
    update_option('my_plugin_activated', '1');
});

// Register deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Code to run when plugin is deactivated
    delete_option('my_plugin_activated');
});
```

## Adding Hooks to Core Files

To make your plugin system more powerful, add hooks to core files:

### Example: Adding Hook to Homepage

```php
// In index.php, before content
do_action('homepage_content_before');

// Your content here

// After content
do_action('homepage_content_after');
```

### Example: Adding Filter to Song Title

```php
// In song-details.php
$song_title = apply_filters('song_title', $song['title']);
```

## Plugin Management

### Activate/Deactivate Plugins

1. Go to **Admin Panel → Plugins**
2. Find your plugin
3. Click **Activate** or **Deactivate**

### Delete Plugins

1. Go to **Admin Panel → Plugins**
2. Find your plugin
3. Click **Delete** (this permanently removes the plugin)

## Best Practices

1. **Use Namespaces**: Prevent conflicts with other plugins
   ```php
   namespace MyPlugin;
   ```

2. **Check Function Existence**: Before using plugin functions
   ```php
   if (function_exists('add_action')) {
       add_action('init', 'my_function');
   }
   ```

3. **Sanitize Input**: Always sanitize user input
   ```php
   $clean_input = sanitize_text_field($_POST['input']);
   ```

4. **Use Options API**: Store settings in database
   ```php
   update_option('my_plugin_setting', $value);
   ```

5. **Error Handling**: Use try-catch blocks
   ```php
   try {
       // Your code
   } catch (Exception $e) {
       error_log('Plugin Error: ' . $e->getMessage());
   }
   ```

## Example Plugin

See `plugins/example-plugin/example-plugin.php` for a complete working example.

## Troubleshooting

### Plugin Not Loading

1. Check plugin folder name matches PHP file name
2. Verify plugin header is correct
3. Check file permissions (should be 644)
4. Check error logs for PHP errors

### Hooks Not Working

1. Verify hook name is correct
2. Check priority (lower numbers run first)
3. Ensure plugin is activated
4. Clear any caches

### Database Errors

1. Ensure database connection is working
2. Check table permissions
3. Verify plugin table exists (created automatically)

## Security Considerations

1. **Validate Input**: Always validate and sanitize user input
2. **Nonce Verification**: Use nonces for form submissions
3. **Capability Checks**: Check user permissions before actions
4. **SQL Injection**: Use prepared statements
5. **XSS Prevention**: Escape output with `htmlspecialchars()`

## Support

For plugin development support:
- Check example plugin in `plugins/example-plugin/`
- Review this documentation
- Check error logs in `logs/` directory

## Future Enhancements

Planned features:
- Plugin update system
- Plugin marketplace
- Plugin dependencies
- Plugin versioning
- More hooks and filters
- Admin UI for hook management

