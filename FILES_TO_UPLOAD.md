# Files to Upload to Live Server

## Updated Files - Installation & License Verification Fix

### 1. **install.php** ✅ REQUIRED
**Location:** Root directory (`/music/install.php`)

**What was fixed:**
- License server API endpoint URL corrected
- Changed from: `https://hylinktech.com/api/verify.php`
- Changed to: `https://hylinktech.com/server/api/verify.php`
- Added better error messages for debugging

**Action:** Upload this file to replace the existing one.

---

## Other Files Updated (Related Fixes)

### 2. **config/database.php** ✅ RECOMMENDED
**Location:** `config/database.php`

**What was fixed:**
- Now uses database credentials from `config.php` instead of hardcoded values
- Prevents admin login database connection errors

**Action:** Upload if you haven't already.

### 3. **install/install-database.php** ✅ RECOMMENDED
**Location:** `install/install-database.php`

**What was fixed:**
- Auto-detects base path (works in root or subdirectory)
- Removed hardcoded `/music/` path

**Action:** Upload if you haven't already.

### 4. **admin/login.php** ✅ RECOMMENDED
**Location:** `admin/login.php`

**What was fixed:**
- Loads config before database connection
- Prevents database connection errors

**Action:** Upload if you haven't already.

### 5. **admin/auth-check.php** ✅ RECOMMENDED
**Location:** `admin/auth-check.php`

**What was fixed:**
- Loads config before database connection
- Prevents authentication errors

**Action:** Upload if you haven't already.

### 6. **index.php** ✅ OPTIONAL (Homepage fixes)
**Location:** Root directory (`/music/index.php`)

**What was fixed:**
- Social media meta tags (og:title, og:description, etc.)
- Homepage title uses slogan from settings
- Better database error handling (prevents 500 errors)

**Action:** Upload if you want homepage sharing and error handling fixes.

---

## Quick Upload Guide

### For License Verification Fix Only:

**Upload this ONE file:**
```
install.php
```

### For Complete Fix (Recommended):

**Upload these files:**
```
install.php
config/database.php
install/install-database.php
admin/login.php
admin/auth-check.php
```

### For Everything Including Homepage Fixes:

**Upload all files above PLUS:**
```
index.php
```

---

## File Locations on Server

After uploading, your file structure should be:

```
/public_html/
├── install.php                    ← Upload this
├── config/
│   └── database.php              ← Upload this
├── install/
│   └── install-database.php     ← Upload this
├── admin/
│   ├── login.php                 ← Upload this
│   └── auth-check.php            ← Upload this
└── index.php                      ← Upload this (optional)
```

---

## Verification After Upload

After uploading the files, verify:

1. **License Verification:**
   - Go to: `https://yourdomain.com/install.php`
   - Try license verification
   - Should now work without 404 error

2. **Admin Login:**
   - Go to: `https://yourdomain.com/admin/login.php`
   - Should connect to database correctly

3. **Homepage:**
   - Visit: `https://yourdomain.com/index.php`
   - Should load without 500 errors

---

## Important Notes

1. **Backup First:** Always backup existing files before uploading
2. **File Permissions:** Keep existing file permissions (usually 644)
3. **Check Paths:** Make sure file paths are correct on your server
4. **Test After Upload:** Test the installation/license verification immediately

---

## What Each File Does

### install.php
- Main installation wizard
- Handles license verification
- **Critical for fixing the 404 error**

### config/database.php
- Database connection class
- Uses credentials from config.php
- **Critical for admin login**

### install/install-database.php
- Database installation functions
- Creates tables and config file
- **Critical for clean installation**

### admin/login.php
- Admin login page
- **Critical for admin access**

### admin/auth-check.php
- Authentication check for admin pages
- **Critical for admin security**

### index.php
- Homepage
- Social sharing meta tags
- **Optional - for homepage improvements**

---

## Troubleshooting After Upload

### Still Getting 404 Error?
1. Verify file uploaded correctly
2. Check file permissions (should be 644)
3. Clear browser cache
4. Check if license server is accessible

### Admin Login Not Working?
1. Verify `config/database.php` uploaded
2. Check database credentials in `config/config.php`
3. Verify file permissions

### Installation Not Working?
1. Verify all files uploaded correctly
2. Check file paths match
3. Review error messages in installation wizard

---

## Summary

**Minimum Required (for license fix):**
- `install.php`

**Recommended (for complete fix):**
- `install.php`
- `config/database.php`
- `install/install-database.php`
- `admin/login.php`
- `admin/auth-check.php`

**Optional (for homepage improvements):**
- `index.php`

