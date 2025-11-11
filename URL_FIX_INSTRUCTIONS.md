# URL Rewrite Fix Instructions

## The Problem
Song detail pages show "404 Not Found" because Apache doesn't know how to route friendly URLs like `/song/song-title-by-artist-name` to `song-details.php`.

## The Solution
I've added URL rewrite rules to `.htaccess`. However, if it's still not working, check the following:

### 1. Enable mod_rewrite in Apache
Open `C:\xampp\apache\conf\httpd.conf` and make sure this line is NOT commented out:
```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

### 2. Enable .htaccess Override
In the same `httpd.conf` file, find the `<Directory>` section for your document root (usually `C:/xampp/htdocs`) and ensure:
```apache
<Directory "C:/xampp/htdocs">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

### 3. Restart Apache
After making changes to `httpd.conf`, restart Apache from XAMPP Control Panel.

### 4. Test the Rewrite
1. Visit: `http://localhost/music/test-rewrite.php`
2. Try accessing: `http://localhost/music/song/test-song-by-test-artist`
3. Check if it redirects to `song-details.php?slug=test-song-by-test-artist`

### 5. Alternative: Direct Access
If rewrite still doesn't work, you can temporarily access songs directly:
- `http://localhost/music/song-details.php?slug=song-title-by-artist-name`
- Or by ID: `http://localhost/music/song-details.php?id=123`

### 6. Check Apache Error Log
If still not working, check Apache error log:
- `C:\xampp\apache\logs\error.log`
- Look for rewrite-related errors

## Current .htaccess Rules
The `.htaccess` file now contains:
```apache
RewriteRule ^song/(.+)$ song-details.php?slug=$1 [L,QSA]
RewriteRule ^artist/(.+)$ artist-profile.php?slug=$1 [L,QSA]
RewriteRule ^album/(.+)$ album-details.php?slug=$1 [L,QSA]
```

These rules work when the `.htaccess` file is in the same directory as the PHP files.

## If Still Not Working
1. Check if `.htaccess` file is actually being read (check Apache error log)
2. Verify mod_rewrite is loaded: `httpd -M | findstr rewrite` (run from Apache bin directory)
3. Try accessing `http://localhost/music/test-rewrite.php` to see what Apache sees




