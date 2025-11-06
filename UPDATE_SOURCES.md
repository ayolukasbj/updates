# Update Sources Configuration Guide

## Overview

The platform supports multiple update sources:
1. **License Server** (default) - Updates from your license management server
2. **GitHub Releases** - Direct download from GitHub repository releases
3. **cPanel File Manager** - Local file paths for files uploaded via cPanel

## Configuration Methods

### Method 1: License Server (Default)

When you create an update in the license server, you can provide any of these URL types:

#### GitHub Release URL
```
https://github.com/yourusername/yourrepo/releases/latest
```
or
```
https://github.com/yourusername/yourrepo/releases/tag/v1.1.0
```

The system will automatically:
- Fetch release info from GitHub API
- Find the ZIP asset
- Download it directly

#### cPanel File Path
```
/home/username/public_html/updates/update-v1.1.0.zip
```
or relative path:
```
../updates/update-v1.1.0.zip
```

The system will:
- Check if file exists locally
- Copy it to update directory
- Proceed with installation

#### Direct HTTP/HTTPS URL
```
https://yourdomain.com/updates/update-v1.1.0.zip
```

Standard download from any web server.

### Method 2: Direct GitHub Integration

To use GitHub releases directly without license server:

1. **Modify `admin/check-updates.php`**:
   - Change the `$updates_api_url` to point to GitHub API
   - Or add a setting in admin panel to choose update source

2. **GitHub API Endpoint**:
   ```
   https://api.github.com/repos/OWNER/REPO/releases/latest
   ```

### Method 3: cPanel File Manager

1. **Upload Update ZIP**:
   - Log into cPanel
   - Navigate to File Manager
   - Upload your update ZIP file to a directory (e.g., `/public_html/updates/`)

2. **Use Full Path in License Server**:
   - When creating update, use the full server path:
   ```
   /home/username/public_html/updates/update-v1.1.0.zip
   ```

3. **Or Use Relative Path**:
   ```
   ../updates/update-v1.1.0.zip
   ```

## Examples

### Example 1: GitHub Release
```
Download URL: https://github.com/yourusername/music-platform/releases/latest
Version: 1.1.0
```

### Example 2: cPanel File Path
```
Download URL: /home/username/public_html/updates/update-v1.1.0.zip
Version: 1.1.0
```

### Example 3: Direct URL
```
Download URL: https://yourdomain.com/updates/update-v1.1.0.zip
Version: 1.1.0
```

## Security Notes

- GitHub downloads use official GitHub API
- Local file paths are validated for security
- All downloads are verified before extraction
- Backups are created automatically before updates

## Troubleshooting

### GitHub Downloads Fail
- Check if repository is public or use GitHub token
- Verify release exists and has ZIP asset
- Check network connectivity

### cPanel Paths Not Working
- Verify file permissions (readable)
- Use absolute paths when possible
- Check file exists before creating update

### Download Timeout
- Increase timeout in `install-update.php` (currently 300 seconds)
- Check file size (large files may need more time)

