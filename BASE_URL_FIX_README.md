# Base URL Fix Update Package

## What This Update Fixes

This update fixes the hardcoded `/music/` path issue that was causing URLs to always point to `example.com/music` even when installed on root domains or different directories.

## Changes Made

### 1. Auto-Detection of Base Path
- **File**: `config/config.php`
- **Change**: Base path is now automatically detected from the script location
- **Result**: Works on root domains (`/`) or any subdirectory (`/music/`, `/app/`, etc.)

### 2. Added `base_url()` Helper Function
- **File**: `config/config.php`
- **Function**: `base_url($path = '')` - Returns correct base path for any URL
- **Usage**: Replace hardcoded `/music/` with `base_url('path')`

### 3. Fixed All Hardcoded News URLs
- **Files Updated**:
  - `index.php` - All news links now use `base_url()`
  - `news.php` - News listing links fixed
  - `news-details.php` - Previous/Next and related news links fixed
  - `includes/header.php` - Search results news URLs fixed

### 4. Fixed License Management URL
- **File**: `config/license.php`
- **Change**: License activation link now uses `BASE_PATH` constant

### 5. Updated Installation Script
- **File**: `install/install-database.php`
- **Change**: Installation now auto-detects base path instead of hardcoding `/music/`

### 6. Admin Footer Appreciation
- **File**: `admin/includes/footer.php`
- **Change**: Added footer with appreciation to HyLink Technologies (U) SMC
- **Link**: https://hylinktech.com

## Files Included in Update

```
update-base-url-fix/
├── config/
│   └── config.php (auto-detect base path + base_url() function)
├── config/
│   └── license.php (fixed license activation URL)
├── index.php (all /music/news/ links fixed)
├── news.php (news listing links fixed)
├── news-details.php (previous/next/related links fixed)
├── includes/
│   └── header.php (search results URLs fixed)
├── install/
│   └── install-database.php (auto-detect in installation)
└── admin/
    └── includes/
        └── footer.php (HyLink Technologies appreciation)
```

## How to Apply This Update

### Option 1: Via GitHub Update System (Recommended)

1. **Push these changes to GitHub**:
   ```bash
   git add .
   git commit -m "Fix base URL auto-detection - remove hardcoded /music/"
   git push origin main
   ```

2. **Create update in License Server**:
   - Version: `1.1.2` (or your next version)
   - Download URL: `https://github.com/ayolukasbj/updates`
   - Changelog:
     ```
     - Fixed base URL auto-detection (removes hardcoded /music/)
     - Works on root domains and any subdirectory
     - Added base_url() helper function
     - Fixed all news URLs
     - Added admin footer appreciation to HyLink Technologies
     ```

3. **Clients install automatically**

### Option 2: Manual Update

1. **Download the updated files** from GitHub
2. **Upload to your server**, replacing the existing files
3. **Clear cache** if you have caching enabled
4. **Test** that URLs work correctly

## Testing After Update

1. **Check Base Path Detection**:
   - Visit any page
   - Check that URLs don't include `/music/` if installed on root
   - Check that URLs include correct subdirectory if installed in one

2. **Test News Links**:
   - Click on news articles from homepage
   - Verify URLs are correct
   - Check previous/next navigation on news details page

3. **Test Admin Footer**:
   - Visit any admin page
   - Scroll to bottom
   - Verify "Powered by HyLink Technologies (U) SMC" appears
   - Click link to verify it goes to https://hylinktech.com

## Technical Details

### Base Path Detection Logic

```php
// Auto-detect base path from script location
$script_path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$base_path = $script_path === '/' ? '/' : rtrim($script_path, '/') . '/';

// If installed in root, base_path should be '/'
if (strpos($script_path, '/admin') !== false) {
    // We're in admin folder, go up one level
    $base_path = dirname($script_path) === '/' ? '/' : dirname($script_path) . '/';
} elseif ($script_path === '/' || empty($script_path)) {
    $base_path = '/';
}
```

### Using base_url() Function

**Before:**
```php
$link = '/music/news/' . $news_id;
```

**After:**
```php
$link = base_url('news/' . $news_id);
```

This automatically handles:
- Root installation: `/news/123`
- Subdirectory: `/music/news/123`
- Any directory: `/app/news/123`

## Support

If you encounter any issues after applying this update:
1. Check that `BASE_PATH` constant is defined correctly
2. Verify file permissions are correct
3. Clear browser cache
4. Check server error logs

---

**Version**: 1.1.2  
**Date**: 2025-01-XX  
**Fixes**: Base URL auto-detection, hardcoded paths, admin footer












