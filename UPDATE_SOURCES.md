# Update Sources Configuration Guide

## Overview

The platform supports multiple update sources:
1. **GitHub** (Recommended) - Direct download from GitHub repository ⭐
2. **License Server** - Updates from your license management server
3. **cPanel File Manager** - Local file paths for files uploaded via cPanel

> **Note:** GitHub updates are working perfectly! See `GITHUB_UPDATES_GUIDE.md` for quick reference.

## Configuration Methods

### Method 1: GitHub (Recommended) ⭐

**GitHub updates are working perfectly!** This is the easiest and most reliable method.

#### Quick Setup:
1. Push your code to GitHub: `https://github.com/ayolukasbj/updates`
2. In License Server, create update with URL: `https://github.com/ayolukasbj/updates`
3. Done! Clients can install automatically.

**See `GITHUB_UPDATES_GUIDE.md` for complete guide.**

#### Supported GitHub URL Formats:
- Repository URL: `https://github.com/ayolukasbj/updates`
- Specific branch: `https://github.com/ayolukasbj/updates/tree/develop`
- Releases: `https://github.com/ayolukasbj/updates/releases/latest`

The system automatically:
- Downloads repository as ZIP
- Handles folder structure
- Installs files correctly

---

### Method 2: License Server

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

#### GitHub Repository URL (No Releases)
If your repository doesn't have releases, you can use the repository URL directly:
```
https://github.com/yourusername/yourrepo
```
or
```
https://github.com/yourusername/yourrepo/
```

The system will automatically:
- Download the repository as a ZIP file from the main branch
- Extract and install the files
- Handle the repository folder structure automatically

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

#### Option A: Direct File Path (Same Server)

1. **Upload Update ZIP**:
   - Log into cPanel File Manager
   - Navigate to your updates directory (e.g., `/home/username/server/updates/`)
   - Upload your update ZIP file

2. **Set File Permissions**:
   - Right-click the ZIP file → "Change Permissions"
   - Set to `644` (readable by web server)

3. **Use Full Path in License Server**:
   ```
   /home/username/server/updates/update-v1.1.0.zip
   ```

**Note:** This only works if the license server and client platform are on the same server.

#### Option B: Web-Accessible URL (Different Servers)

1. **Create Web Directory**:
   - Create directory: `/home/username/public_html/updates/`
   - Or use: `/home/username/domain.com/public_html/updates/`

2. **Upload Update ZIP**:
   - Upload your ZIP file to this directory
   - Set permissions to `644`

3. **Test Accessibility**:
   - Open in browser: `https://yourdomain.com/updates/update-v1.1.0.zip`
   - Should download the file

4. **Use HTTP URL in License Server**:
   ```
   https://yourdomain.com/updates/update-v1.1.0.zip
   ```

**See `CPANEL_UPDATE_SETUP.md` for detailed setup instructions.**

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

