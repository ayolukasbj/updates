# cPanel Update Files Setup Guide

## Overview

This guide explains how to set up update files in cPanel so they can be accessed by the update system.

## Your Current Setup

Based on your cPanel file manager URL, your updates directory is:
```
/home/gospelki/hylinktech.com/server/updates
```

## Option 1: Use Direct File Path (Recommended for Same Server)

If your license server and client platform are on the same server, you can use the direct file path.

### Steps:

1. **Upload your update ZIP file** to:
   ```
   /home/gospelki/hylinktech.com/server/updates/update-v1.1.1.zip
   ```

2. **In License Server**, when creating an update, use:
   ```
   Download URL: /home/gospelki/hylinktech.com/server/updates/update-v1.1.1.zip
   ```

3. **Set file permissions** (via cPanel File Manager):
   - Right-click the ZIP file
   - Select "Change Permissions"
   - Set to `644` (readable by web server)

### Advantages:
- ✅ Fast (no download needed)
- ✅ Secure (files stay on server)
- ✅ No bandwidth usage

### Limitations:
- ❌ Only works if both systems are on the same server
- ❌ Requires correct file permissions

---

## Option 2: Make Files Web-Accessible (Recommended for Different Servers)

If your license server and client platforms are on different servers, make the files accessible via HTTP/HTTPS.

### Steps:

1. **Create a web-accessible directory** in your license server:
   ```
   /home/gospelki/hylinktech.com/server/public_html/updates/
   ```
   Or if your server is at `hylinktech.com`:
   ```
   /home/gospelki/hylinktech.com/public_html/updates/
   ```

2. **Upload your update ZIP file** to this directory:
   ```
   /home/gospelki/hylinktech.com/public_html/updates/update-v1.1.1.zip
   ```

3. **Set file permissions**:
   - Right-click the ZIP file → "Change Permissions" → `644`

4. **Test accessibility**:
   Open in browser:
   ```
   https://hylinktech.com/updates/update-v1.1.1.zip
   ```
   Or if using a subdomain:
   ```
   https://server.hylinktech.com/updates/update-v1.1.1.zip
   ```

5. **In License Server**, when creating an update, use:
   ```
   Download URL: https://hylinktech.com/updates/update-v1.1.1.zip
   ```

### Advantages:
- ✅ Works across different servers
- ✅ Standard HTTP download
- ✅ Easy to test

### Security (Optional):

To protect your update files, create a `.htaccess` file in the updates directory:

```apache
# Allow direct access to ZIP files
<FilesMatch "\.zip$">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Optional: Add basic authentication
AuthType Basic
AuthName "Update Files"
AuthUserFile /home/gospelki/.htpasswd
Require valid-user
```

---

## Option 3: Use GitHub (Already Supported)

If you prefer, you can use GitHub:

1. **Upload to GitHub repository**
2. **Create a release** (optional) or use repository URL
3. **In License Server**, use:
   ```
   Download URL: https://github.com/ayolukasbj/updates
   ```

---

## Quick Setup for Your Server

Based on your setup (`/home/gospelki/hylinktech.com/server/updates`), here's the quickest solution:

### If License Server and Clients are on Same Server:

1. Upload ZIP to: `/home/gospelki/hylinktech.com/server/updates/update-v1.1.1.zip`
2. Use path: `/home/gospelki/hylinktech.com/server/updates/update-v1.1.1.zip`

### If License Server and Clients are on Different Servers:

1. Create directory: `/home/gospelki/hylinktech.com/public_html/updates/`
2. Upload ZIP to: `/home/gospelki/hylinktech.com/public_html/updates/update-v1.1.1.zip`
3. Use URL: `https://hylinktech.com/updates/update-v1.1.1.zip`

---

## Troubleshooting

### "Local file not found" Error

**Solution:**
- Verify the file path is correct
- Check file permissions (should be `644`)
- Ensure the file exists on the server
- Try using the web-accessible URL instead

### "File is not readable" Error

**Solution:**
- Change file permissions to `644`
- Check directory permissions (should be `755`)
- Verify the web server user can read the file

### "Download failed" Error (HTTP URLs)

**Solution:**
- Test the URL in a browser first
- Check if the file is accessible via HTTP
- Verify SSL certificate (for HTTPS)
- Check server firewall settings

### File Too Large

**Solution:**
- Compress files better
- Split into multiple smaller updates
- Increase PHP `upload_max_filesize` and `post_max_size`

---

## File Naming Convention

Recommended naming:
```
update-v1.1.1.zip
update-v1.2.0.zip
update-v2.0.0.zip
```

This makes it easy to identify versions.

---

## Security Best Practices

1. **Don't expose sensitive files** - Only include update files in the ZIP
2. **Use HTTPS** - Always use HTTPS for web-accessible files
3. **Set proper permissions** - Files should be `644`, directories `755`
4. **Regular cleanup** - Remove old update files periodically
5. **Monitor access** - Check server logs for unauthorized access

---

## Example Setup

### Directory Structure:
```
/home/gospelki/hylinktech.com/
├── server/
│   ├── updates/                    # For same-server access
│   │   └── update-v1.1.1.zip
│   └── public_html/
│       └── updates/                # For web access
│           └── update-v1.1.1.zip
```

### License Server Configuration:
```
Version: 1.1.1
Download URL: /home/gospelki/hylinktech.com/server/updates/update-v1.1.1.zip
```
OR
```
Version: 1.1.1
Download URL: https://hylinktech.com/updates/update-v1.1.1.zip
```

---

**Need Help?** Check the main `UPDATE_SOURCES.md` file for more information.












