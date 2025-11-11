# Live Server Fix Guide

## Issues on Live Server:
1. Homepage showing only header
2. Artist profile pages not working
3. Login page not working
4. Everything works on localhost but not live

## Debugging Steps:

### Step 1: Run Debug Script
1. Upload `debug-live.php` to your live server
2. Access: `https://tesotalents.com/debug-live.php`
3. Check the report for errors

### Step 2: Enable Debug Mode (Temporary)
1. Edit `config/config.php` on live server
2. Change: `define('DEBUG_MODE', false);` to `define('DEBUG_MODE', true);`
3. Refresh pages to see errors
4. **IMPORTANT:** Set back to `false` after debugging!

### Step 3: Check Common Issues

#### Database Connection
- Verify database credentials in `config/database.php`
- Check if database exists
- Check if database user has proper permissions

#### File Permissions
- Ensure `uploads/` directory is writable (755 or 775)
- Check all subdirectories in `uploads/`

#### Missing Files
- Verify all files are uploaded
- Check file paths (case-sensitive on Linux)

#### PHP Errors
- Check error logs: `/var/log/apache2/error.log` or cPanel error logs
- Check PHP error log location in cPanel

### Step 4: Common Fixes

#### Fix 1: Database Connection
If database connection fails:
```php
// Check config/database.php
// Verify DB_HOST, DB_NAME, DB_USER, DB_PASS
```

#### Fix 2: File Paths
If files not found:
- Check case sensitivity (Linux is case-sensitive)
- Verify file paths are correct
- Check `.htaccess` is working

#### Fix 3: Session Issues
If session errors:
- Check `session.save_path` in php.ini
- Ensure session directory is writable
- Check session cookies are being set

### Step 5: Files to Check

1. **config/config.php**
   - Verify database credentials
   - Check SITE_URL and BASE_PATH

2. **config/database.php**
   - Verify database connection settings
   - Check if Database class exists

3. **includes/song-storage.php**
   - Verify file exists
   - Check database queries

4. **classes/User.php, Song.php, Artist.php**
   - Verify all class files exist
   - Check for syntax errors

### Step 6: Error Logs

Check these locations for error logs:
- cPanel Error Logs
- `/var/log/apache2/error.log`
- `/var/log/php_errors.log`
- Check `error_log` in PHP settings

### Step 7: Quick Fixes Applied

Files updated with better error handling:
- ✅ `index.php` - Added error handling and debug mode
- ✅ `artist-profile-mobile.php` - Added error handling
- ✅ `login.php` - Added error handling
- ✅ `config/config.php` - Added DEBUG_MODE constant
- ✅ `debug-live.php` - New debug script

## After Debugging:

1. **Disable Debug Mode:**
   ```php
   define('DEBUG_MODE', false);
   ```

2. **Delete debug-live.php** (security)

3. **Check error logs** regularly

4. **Monitor** for any new errors

## Next Steps:

1. Upload `debug-live.php` to live server
2. Run it and check the report
3. Fix any errors found
4. Test all pages
5. Disable debug mode
6. Delete debug script












