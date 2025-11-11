# Updates Folder Setup

## Updates Folder Location

Your update files will be stored in:
```
C:\Users\HYLINK\Desktop\music - Copy\updates
```

## How to Prepare Updates

### Method 1: Using prepare-update.php Script

1. **Run the script**:
   ```bash
   php prepare-update.php
   ```

2. **The script will**:
   - Copy all changed files to the updates folder
   - Maintain the directory structure
   - Show progress for each file

3. **Create ZIP package**:
   - Navigate to: `C:\Users\HYLINK\Desktop\music - Copy\updates`
   - Select all files and folders
   - Create a ZIP file (e.g., `update-v1.2.0.zip`)

4. **Upload to GitHub**:
   - Push the ZIP to your GitHub repository
   - Or create a release with the ZIP as an asset

### Method 2: Manual Copy

1. **Navigate to updates folder**:
   ```
   C:\Users\HYLINK\Desktop\music - Copy\updates
   ```

2. **Copy changed files** maintaining directory structure:
   ```
   updates/
   ├── config/
   │   ├── config.php
   │   └── license.php
   ├── includes/
   │   ├── header.php
   │   └── ads.php
   ├── admin/
   │   ├── includes/
   │   │   └── footer.php
   │   └── api/
   │       └── install-update.php
   ├── install/
   │   └── install-database.php
   ├── index.php
   ├── news.php
   ├── news-details.php
   ├── song-details.php
   ├── artist-profile-mobile.php
   ├── album-details.php
   └── .htaccess
   ```

3. **Create ZIP** and upload to GitHub

## Files Changed in This Update

### Base URL Fix (Removed /music/ hardcoding):
- `config/config.php` - Auto-detection + base_url() function
- `config/license.php` - Fixed license activation URL
- `index.php` - All news links use base_url()
- `news.php` - News listing links fixed
- `news-details.php` - Previous/Next/Related links fixed
- `includes/header.php` - Base URL and search API URLs fixed
- `includes/ads.php` - Base path detection
- `song-details.php` - All base URLs and API paths fixed
- `artist-profile-mobile.php` - Base URL fixed
- `album-details.php` - Base path auto-detection
- `install/install-database.php` - Auto-detection in installation
- `.htaccess` - Removed hardcoded RewriteBase

### Admin Footer:
- `admin/includes/footer.php` - Added HyLink Technologies appreciation

### Update System:
- `admin/api/install-update.php` - Fixed JSON parsing error

## Update Package Structure

When creating the ZIP, maintain this structure:
```
update-v1.2.0.zip
├── config/
│   ├── config.php
│   └── license.php
├── includes/
│   ├── header.php
│   └── ads.php
├── admin/
│   ├── includes/
│   │   └── footer.php
│   └── api/
│       └── install-update.php
├── install/
│   └── install-database.php
├── index.php
├── news.php
├── news-details.php
├── song-details.php
├── artist-profile-mobile.php
├── album-details.php
└── .htaccess
```

## Quick Commands

### Prepare Update:
```bash
php prepare-update.php
```

### Create ZIP (Windows):
```powershell
Compress-Archive -Path "C:\Users\HYLINK\Desktop\music - Copy\updates\*" -DestinationPath "C:\Users\HYLINK\Desktop\music - Copy\updates\update-v1.2.0.zip" -Force
```

### Create ZIP (Manual):
1. Select all files in `updates` folder
2. Right-click → Send to → Compressed (zipped) folder
3. Rename to `update-v1.2.0.zip`

## Next Steps

1. **Test the update** on a staging server first
2. **Create update in License Server**:
   - Version: `1.2.0`
   - Download URL: `https://github.com/ayolukasbj/updates`
   - Changelog: List of changes
3. **Notify clients** to install the update

---

**Note:** The updates folder is now set up and ready to use!












