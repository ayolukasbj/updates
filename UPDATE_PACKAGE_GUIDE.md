# Update Package Guide - Files to Upload

## 📦 What Files to Include in Update Packages

When creating an update ZIP file for your clients, include **ONLY the files that have been changed or added**. This keeps update packages small and installation fast.

### ✅ **Files to INCLUDE:**

#### Core Application Files (if updated)
```
✅ admin/              - Admin panel files (if any changed)
✅ api/                - API endpoints (if any changed)
✅ ajax/               - AJAX handlers (if any changed)
✅ assets/             - CSS, JS, images (if any changed)
✅ classes/            - PHP classes (if any changed)
✅ config/             - Configuration files (EXCEPT config.php)
✅ includes/           - Header, footer, functions (if any changed)
✅ install/            - Installation files (if any changed)
✅ views/              - View templates (if any changed)
```

#### Main PHP Files (if updated)
```
✅ index.php           - Homepage
✅ login.php           - Login page
✅ register.php        - Registration
✅ dashboard.php       - User dashboard
✅ upload.php          - Upload functionality
✅ song-details.php    - Song details page
✅ artist-profile.php  - Artist profile
✅ news.php            - News page
✅ songs.php           - Songs listing
✅ artists.php         - Artists listing
✅ top-100.php         - Top 100 chart
✅ Any other PHP files that were modified
```

#### Configuration Files (if updated)
```
✅ config/database.php - Database config (if structure changed)
✅ config/license.php - License manager (if updated)
✅ .htaccess          - Apache configuration (if updated)
```

#### Static Assets (if updated)
```
✅ assets/css/         - Stylesheets (if updated)
✅ assets/js/          - JavaScript files (if updated)
✅ assets/images/      - Images (if updated)
✅ assets/vendor/      - Third-party libraries (if updated)
```

### ❌ **Files to EXCLUDE (Never Include):**

#### Sensitive/User Data
```
❌ config/config.php   - Contains database credentials, license keys
❌ uploads/            - User-uploaded files (songs, images)
❌ data/*.json         - JSON data files (if using database)
❌ backups/            - Backup files
❌ updates/            - Update files
❌ temp/               - Temporary files
❌ logs/               - Log files
```

#### System/Development Files
```
❌ .git/               - Git repository
❌ .gitignore          - Git ignore file
❌ node_modules/       - Node.js dependencies
❌ *.log               - Log files
❌ .DS_Store           - macOS system files
❌ Thumbs.db           - Windows thumbnails
❌ *.md                - Documentation files (optional)
```

#### Test/Debug Files
```
❌ test-*.php          - Test files
❌ debug-*.php          - Debug files
❌ *-backup.php        - Backup files
❌ *-simple.php        - Test versions
❌ *-working.php        - Test versions
```

## 📁 Update Package Structure Example

Here's an example structure for an update package:

```
update-v1.1.0.zip
├── admin/
│   ├── news.php              (updated)
│   └── songs.php             (updated)
├── includes/
│   └── footer.php            (updated)
├── config/
│   └── license.php           (updated)
├── admin/api/
│   └── install-update.php    (updated)
├── index.php                 (updated)
└── .htaccess                 (updated)
```

## 🎯 Best Practices

### 1. **Include Only Changed Files**
- Don't include entire directories if only 1-2 files changed
- This reduces package size and installation time

### 2. **Maintain Directory Structure**
- Keep the same folder structure as the main platform
- Files should be in the same relative paths

### 3. **Version Your Updates**
- Name ZIP file: `update-v1.1.0.zip` (matches version number)
- Include version in changelog

### 4. **Test Before Distribution**
- Test update package on a test installation first
- Verify all files extract correctly
- Ensure no sensitive data is included

### 5. **Document Changes**
- Include changelog in license server
- List all modified files
- Explain what changed

## 📋 Quick Checklist

Before creating your update ZIP:

- [ ] Only include files that were actually changed
- [ ] Exclude `config/config.php` (contains sensitive data)
- [ ] Exclude `uploads/` directory (user data)
- [ ] Exclude `backups/`, `updates/`, `temp/` directories
- [ ] Exclude test/debug files (`test-*.php`, `debug-*.php`)
- [ ] Maintain proper directory structure
- [ ] Test ZIP file extraction
- [ ] Verify no sensitive credentials included
- [ ] Name file with version number (`update-v1.1.0.zip`)

## 🔍 How to Create Update Package

### Method 1: Manual Selection
1. Create a new folder: `update-v1.1.0/`
2. Copy only changed files maintaining structure
3. Zip the folder: `update-v1.1.0.zip`
4. Upload to GitHub/cPanel

### Method 2: Using Git (Recommended)
```bash
# Create update from specific commit
git archive --format=zip --output=update-v1.1.0.zip HEAD

# Or from specific files
git archive --format=zip --output=update-v1.1.0.zip HEAD admin/news.php includes/footer.php
```

### Method 3: Using File Manager
1. Select only changed files
2. Compress to ZIP
3. Ensure proper folder structure is maintained

## ⚠️ Important Security Notes

1. **Never Include:**
   - Database credentials
   - License keys
   - User uploads
   - Personal data

2. **Always Verify:**
   - No sensitive information in files
   - No hardcoded passwords
   - No API keys exposed

3. **Test First:**
   - Test on development server
   - Verify update process works
   - Check file permissions

## 📝 Example Update Scenarios

### Scenario 1: Bug Fix Update
**Changed Files:**
- `admin/news.php` (fixed WSOD)
- `includes/footer.php` (fixed width)

**Package Contains:**
```
update-v1.0.1.zip
├── admin/
│   └── news.php
└── includes/
    └── footer.php
```

### Scenario 2: Feature Update
**Changed Files:**
- `admin/api/install-update.php` (added GitHub support)
- `admin/check-updates.php` (updated)
- `config/license.php` (updated)

**Package Contains:**
```
update-v1.1.0.zip
├── admin/
│   ├── api/
│   │   └── install-update.php
│   └── check-updates.php
└── config/
    └── license.php
```

### Scenario 3: Major Update
**Changed Files:**
- Multiple admin files
- Core functionality files
- New features added

**Package Contains:**
```
update-v2.0.0.zip
├── admin/
│   ├── (multiple updated files)
├── includes/
│   └── (updated files)
├── api/
│   └── (new endpoints)
└── (other changed files)
```

## 🚀 Upload Methods

### Upload to GitHub
1. Create a new release
2. Attach your `update-v1.1.0.zip` file
3. Use release URL in license server

### Upload to cPanel
1. Log into cPanel File Manager
2. Navigate to `/public_html/updates/` (create if needed)
3. Upload `update-v1.1.0.zip`
4. Use full path: `/home/username/public_html/updates/update-v1.1.0.zip`

### Upload to Web Server
1. Upload ZIP to your web server
2. Make it accessible via HTTP/HTTPS
3. Use URL: `https://yourdomain.com/updates/update-v1.1.0.zip`

## ✅ Final Verification

Before distributing:

1. **Extract Test:**
   ```bash
   unzip -l update-v1.1.0.zip
   ```
   Verify structure is correct

2. **Size Check:**
   - Update packages should be reasonable size
   - Large packages (>50MB) may timeout

3. **File Count:**
   - Include only necessary files
   - Avoid including entire directories

4. **Security Scan:**
   - No credentials in files
   - No sensitive data
   - No user uploads

---

**Remember:** Smaller, focused update packages = faster installation = happier clients! 🎉

