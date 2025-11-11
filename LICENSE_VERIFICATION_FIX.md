# License Verification Fix

## Issue Fixed

**Problem:** HTTP 404 error when verifying license during installation.

**Cause:** The API endpoint URL was incorrect. It was pointing to `/api/verify.php` instead of `/server/api/verify.php`.

**Fix:** Updated the API endpoint URL in `install.php` to:
```php
$license_api_url = 'https://hylinktech.com/server/api/verify.php';
```

---

## Try Again

1. **Refresh the installation page** or go back to Step 1
2. **Enter your license key:** `M32T-3LXV-LSYW-HSPE-D4QG`
3. **Enter your domain:** `lirasqoop.com` (or `lirasqoop.com/music` if that's what's registered in the license server)

**Note about domain:**
- The domain should match **exactly** what's registered in the license server
- If you registered `lirasqoop.com`, use that (without `/music`)
- If you registered `lirasqoop.com/music`, use that (with `/music`)

---

## Verify License Server Setup

Make sure your license server is properly installed and accessible:

1. **Check License Server URL:**
   - Should be accessible at: `https://hylinktech.com/server`
   - Should show the license server dashboard

2. **Check API Endpoint:**
   - Should be accessible at: `https://hylinktech.com/server/api/verify.php`
   - Should return JSON (even if it's an error, it should return JSON, not 404)

3. **Test API Manually:**
   You can test the API endpoint directly by visiting:
   ```
   https://hylinktech.com/server/api/verify.php
   ```
   It should return a JSON response (even if it's an error about missing parameters).

---

## Common Issues

### Still Getting 404 Error

**Possible causes:**
1. License server not installed at `/server/` directory
2. API file not uploaded to `/server/api/verify.php`
3. File permissions issue
4. `.htaccess` blocking access

**Solutions:**
1. Check if `https://hylinktech.com/server/install.php` works
2. Verify the file structure:
   ```
   /server/
   ├── api/
   │   └── verify.php  ← Should exist here
   ├── config/
   └── ...
   ```
3. Check file permissions (should be 644 for PHP files)
4. Check `.htaccess` rules

### License Key Not Found

**Possible causes:**
1. License key doesn't exist in license server database
2. Domain doesn't match
3. License is inactive

**Solutions:**
1. Login to license server admin: `https://hylinktech.com/server/login.php`
2. Check if license key exists in "Licenses" page
3. Verify the domain matches exactly
4. Ensure license status is "Active"

### Domain Mismatch

**Possible causes:**
1. Domain in license server is different from what you're entering
2. Domain includes/excludes www or path

**Solutions:**
1. Check the exact domain in license server
2. Use the exact same format (with or without www, with or without path)
3. Common formats:
   - `lirasqoop.com`
   - `www.lirasqoop.com`
   - `lirasqoop.com/music`
   - `www.lirasqoop.com/music`

---

## Testing Steps

1. **Test License Server Access:**
   ```
   Visit: https://hylinktech.com/server
   Should see: License server dashboard
   ```

2. **Test API Endpoint:**
   ```
   Visit: https://hylinktech.com/server/api/verify.php
   Should see: JSON response (even if error about missing parameters)
   ```

3. **Test License Verification:**
   ```
   Use installation wizard
   Enter license key and domain
   Should verify successfully
   ```

---

## Files Modified

- `install.php` - Fixed API endpoint URL from `/api/verify.php` to `/server/api/verify.php`

---

## Next Steps

After fixing the URL, try the installation again. If you still get errors:

1. Check the error message (it will now show the API endpoint URL)
2. Verify the license server is accessible
3. Check the license key and domain in the license server admin panel
4. Review error logs on the license server

---

## Support

If you continue to have issues:

1. Check license server error logs
2. Verify file structure and permissions
3. Test API endpoint manually
4. Check license key and domain in license server database

