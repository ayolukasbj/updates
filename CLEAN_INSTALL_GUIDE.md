# Clean Installation Guide for Live Server

## Issues Fixed

### 1. Database Connection Issues ✅
- **Problem:** `config/database.php` had hardcoded localhost values instead of using config constants
- **Fix:** Updated `Database` class to load and use constants from `config/config.php`
- **Result:** Admin login now uses correct database credentials from installation

### 2. Localhost References ✅
- **Problem:** Install script had hardcoded `localhost` URLs for license server
- **Fix:** Auto-detection based on environment (localhost vs production)
- **Result:** Automatically uses production URLs when installed on live server

### 3. Base Path Hardcoding ✅
- **Problem:** Config file was hardcoding `/music/` base path
- **Fix:** Auto-detection of base path from script location
- **Result:** Works correctly whether installed in root or subdirectory

### 4. Admin Login Database Connection ✅
- **Problem:** Admin login failing to connect to database
- **Fix:** Ensured config.php is loaded before database.php
- **Result:** Admin login now works correctly after installation

---

## How to Reinstall on Live Server

### Step 1: Delete Existing Config
```bash
# Delete the config file to allow reinstallation
rm config/config.php
```

Or via FTP/cPanel File Manager:
- Navigate to `config/` folder
- Delete `config.php`

### Step 2: Run Fresh Installation

1. **Access Installation:**
   ```
   https://yourdomain.com/install.php
   ```

2. **Step 1: License Verification**
   - Enter license key from license server
   - Enter your domain (e.g., `yourdomain.com`)
   - The system will auto-detect production license server URLs

3. **Step 2: Site Configuration**
   - Enter site name, slogan, admin credentials
   - All settings will be stored in database

4. **Step 3: Database Configuration**
   - Enter database credentials from your hosting
   - Test connection before proceeding

5. **Step 4: Installation**
   - Automatic installation will create clean config
   - No localhost references will be included
   - Base path will be auto-detected

### Step 3: Verify Installation

1. **Check Config File:**
   - Open `config/config.php`
   - Should have:
     - Correct database credentials
     - Production license server URL (not localhost)
     - Auto-detected base path
     - No localhost references

2. **Test Admin Login:**
   - Go to: `https://yourdomain.com/admin/login.php`
   - Login with admin credentials
   - Should connect successfully

3. **Test Homepage:**
   - Go to: `https://yourdomain.com/index.php`
   - Should load without errors

---

## What Changed

### Files Modified:

1. **`config/database.php`**
   - Now loads `config.php` to get database constants
   - Uses installation database credentials
   - No more hardcoded localhost values

2. **`install.php`**
   - Auto-detects environment (local vs production)
   - Uses production license URLs on live server
   - No manual URL changes needed

3. **`install/install-database.php`**
   - Auto-detects base path from script location
   - Works in root directory or subdirectory
   - No hardcoded `/music/` path

4. **`admin/login.php`**
   - Loads config.php before database.php
   - Ensures database connection uses correct credentials

5. **`admin/auth-check.php`**
   - Loads config.php before database.php
   - Prevents database connection errors

---

## Production Config Example

After installation, `config/config.php` should look like:

```php
<?php
// Auto-generated during installation

define('SITE_INSTALLED', true);
define('SITE_NAME', 'Your Site Name');

// Auto-detected URLs
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'yourdomain.com';
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$base_path = ($script_dir === '/' || $script_dir === '\\') ? '/' : $script_dir . '/';
define('SITE_URL', $protocol . $host . $base_path);
define('BASE_PATH', $base_path);

// Database (from installation)
define('DB_HOST', 'your_host');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

// License (production URLs)
define('LICENSE_SERVER_URL', 'https://hylinktech.com/server');
define('LICENSE_KEY', 'YOUR_LICENSE_KEY');
```

---

## Troubleshooting

### Issue: Admin Login Still Fails

**Solution:**
1. Check `config/config.php` exists and has correct database credentials
2. Verify database credentials in hosting cPanel
3. Check error logs: `cPanel → Error Logs`
4. Ensure `config.php` is readable (644 permissions)

### Issue: Database Connection Error

**Solution:**
1. Verify database host (might not be `localhost` on some hosts)
2. Check database user has privileges
3. Test connection in cPanel → phpMyAdmin
4. Check database name spelling

### Issue: Still Shows Localhost URLs

**Solution:**
1. Delete `config/config.php`
2. Re-run installation
3. Make sure you're accessing from live domain (not localhost)

### Issue: Base Path Wrong

**Solution:**
- The script auto-detects base path
- If installed in root: base path = `/`
- If installed in subdirectory: base path = `/subdirectory/`
- Check `$_SERVER['SCRIPT_NAME']` in your environment

---

## Quick Reinstall Command (SSH)

If you have SSH access:

```bash
# Backup existing config
cp config/config.php config/config.php.backup

# Delete config to allow reinstall
rm config/config.php

# Set permissions
chmod 755 uploads/
chmod 644 config/

# Now run installation via browser
```

---

## Verification Checklist

After reinstallation, verify:

- [ ] `config/config.php` has production database credentials
- [ ] `config/config.php` has production license server URL (not localhost)
- [ ] Base path is correctly auto-detected
- [ ] Admin login works
- [ ] Homepage loads without errors
- [ ] No localhost references in config file
- [ ] Database connection successful
- [ ] All admin pages load correctly

---

## Support

If issues persist:
1. Check error logs in cPanel
2. Enable PHP error display temporarily
3. Verify all file permissions
4. Test database connection manually
5. Check `.htaccess` file exists

