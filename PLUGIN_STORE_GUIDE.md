# Plugin Store System Guide

## Overview

The Plugin Store system allows you to:
1. Upload plugins to the License Server
2. Browse and install plugins from the License Server to your platform
3. Manage plugins centrally through the License Server

## Directory Structure

### Plugin Storage
- **Local Plugin Development**: `C:\Users\HYLINK\Desktop\music - Copy\plugins\`
- **Platform Plugins**: `plugins/` (in your music platform root)
- **License Server Plugins**: `license-server/plugins/` (uploaded plugins)

## License Server - Plugin Upload

### Access
Navigate to: **License Server Admin → Plugin Store**

### Features
1. **Upload Plugin ZIP**
   - Drag & drop or select a ZIP file
   - ZIP should contain a folder with plugin name and main PHP file
   - Plugin metadata is automatically extracted from plugin header

2. **Plugin Management**
   - View all uploaded plugins
   - See download counts
   - Delete plugins

### Plugin ZIP Structure
```
plugin-name/
├── plugin-name.php (main plugin file with header)
├── includes/
│   └── class-plugin.php
├── admin/
│   └── settings.php
└── README.md (optional)
```

### Plugin Header Format
```php
<?php
/**
 * Plugin Name: Your Plugin Name
 * Plugin URI: https://your-plugin-url.com
 * Description: Plugin description
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 */
```

## Platform - Plugin Store

### Access
Navigate to: **Admin Panel → Plugin Store**

### Configuration
1. **License Server URL**
   - Enter your License Server URL
   - Example: `http://your-license-server.com`
   - Click "Save"

### Features
1. **Browse Plugins**
   - View all available plugins from License Server
   - See plugin details: name, version, description, author
   - Check download counts

2. **Install Plugins**
   - Click "Install" button on any plugin
   - Plugin is automatically downloaded, extracted, and activated
   - Installation status is shown (Installed/Active)

3. **Plugin Status**
   - **Installed & Active**: Plugin is installed and running
   - **Installed (Inactive)**: Plugin is installed but not activated
   - Click "Activate" to activate inactive plugins

## API Endpoints

### License Server API

#### List Plugins
```
GET /api/plugin-store.php?action=list&license_key=YOUR_KEY
```

Response:
```json
{
  "success": true,
  "plugins": [
    {
      "id": 1,
      "plugin_name": "MP3 Tagger",
      "plugin_slug": "mp3-tagger",
      "version": "1.0.0",
      "description": "Manages ID3 tags for MP3 files",
      "author": "Your Name",
      "download_count": 5
    }
  ]
}
```

#### Download Plugin
```
GET /api/plugin-store.php?action=download&plugin_slug=mp3-tagger&license_key=YOUR_KEY
```

Returns: ZIP file download

#### Get Plugin Info
```
GET /api/plugin-store.php?action=info&plugin_slug=mp3-tagger&license_key=YOUR_KEY
```

Response:
```json
{
  "success": true,
  "plugin": {
    "id": 1,
    "plugin_name": "MP3 Tagger",
    "plugin_slug": "mp3-tagger",
    "version": "1.0.0",
    "description": "Manages ID3 tags for MP3 files",
    "author": "Your Name",
    "download_count": 5
  }
}
```

## Workflow

### For Plugin Developers

1. **Develop Plugin**
   - Create plugin in `C:\Users\HYLINK\Desktop\music - Copy\plugins\plugin-name\`
   - Follow plugin structure guidelines
   - Add proper plugin header

2. **Create ZIP**
   - Zip the plugin folder
   - Name it: `plugin-name.zip`

3. **Upload to License Server**
   - Go to License Server Admin → Plugin Store
   - Upload the ZIP file
   - Plugin is now available for distribution

### For Platform Administrators

1. **Configure License Server**
   - Go to Admin Panel → Plugin Store
   - Enter License Server URL
   - Save configuration

2. **Browse & Install**
   - View available plugins
   - Click "Install" on desired plugins
   - Plugins are automatically activated

3. **Manage Plugins**
   - Go to Admin Panel → Plugins
   - Activate/Deactivate plugins
   - View plugin details

## Database Tables

### License Server: `plugins_store`
```sql
CREATE TABLE plugins_store (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plugin_name VARCHAR(255) NOT NULL,
    plugin_slug VARCHAR(255) NOT NULL UNIQUE,
    version VARCHAR(50) DEFAULT '1.0.0',
    description TEXT,
    author VARCHAR(255),
    file_path VARCHAR(500),
    zip_path VARCHAR(500),
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Platform: `plugins` (managed by PluginLoader)
- Stores active/inactive plugin status
- Managed automatically by plugin system

## Security

- License key verification (optional for public store)
- File validation on upload
- Secure file extraction
- Path traversal protection

## Troubleshooting

### Plugin Not Appearing in Store
- Check License Server URL is correct
- Verify license key is valid
- Check server connectivity

### Installation Fails
- Verify ZIP file structure
- Check plugin header format
- Ensure plugin file exists in ZIP
- Check file permissions

### Plugin Not Activating
- Check plugin file path
- Verify plugin header is correct
- Check for PHP errors in logs

## Example: MP3 Tagger Plugin

The MP3 Tagger plugin has been moved to:
- **Development**: `C:\Users\HYLINK\Desktop\music - Copy\plugins\mp3-tagger\`
- **Platform**: `plugins/mp3-tagger/`

To distribute:
1. Create ZIP from development folder
2. Upload to License Server
3. Install from Platform Plugin Store

