# Production Setup Guide

## Critical Steps Before Going Live

### 1. Update Configuration (`config/config.php`)

#### Generate New Encryption Key
```bash
php -r "echo bin2hex(random_bytes(32));"
```
Copy the output and replace `ENCRYPTION_KEY` in `config/config.php`

#### Update Database Credentials
Replace:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'music_streaming');
define('DB_USER', 'root');
define('DB_PASS', '');
```
With your production database credentials.

#### Set Environment
Change line 89 in `config/config.php`:
```php
define('ENVIRONMENT', 'production');
```

#### Update API Keys (if using)
- SMTP credentials (for email)
- PayPal API keys (if using payments)
- Stripe API keys (if using payments)
- Social media API keys (if using social login)

### 2. Security Settings

#### Session Security
Add to `config/config.php` after session_start():
```php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Only if using HTTPS
ini_set('session.cookie_samesite', 'Strict');
```

#### File Permissions
Set proper permissions:
```bash
chmod 755 uploads/
chmod 755 logs/
chmod 644 config/config.php
chmod 644 .htaccess
```

#### Protect Sensitive Files
- Move `config/` directory outside web root if possible
- Protect `database/` directory (already in .htaccess)
- Protect `logs/` directory (already in .htaccess)

### 3. Remove Debug/Test Files

#### Option 1: Delete (Recommended for Production)
```bash
# Delete all debug files
rm debug-*.php
rm test-*.php
rm *-backup.php
rm *-working.php
rm *-simple.php
```

#### Option 2: Move to Protected Directory
```bash
mkdir ../protected
mv debug-*.php ../protected/
mv test-*.php ../protected/
```

### 4. Database Setup

#### Create Production Database User
```sql
CREATE USER 'music_prod'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE ON music_streaming.* TO 'music_prod'@'localhost';
FLUSH PRIVILEGES;
```

#### Run Database Schema
```bash
mysql -u music_prod -p music_streaming < database/schema.sql
```

### 5. SSL/HTTPS Setup

#### Install SSL Certificate
- Use Let's Encrypt (free)
- Or purchase SSL certificate

#### Force HTTPS
Uncomment these lines in `.htaccess`:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 6. Error Logging

#### Create Log Directory
```bash
mkdir -p logs/
chmod 755 logs/
```

#### Check Log File Location
Ensure `logs/php-errors.log` is writable:
```bash
touch logs/php-errors.log
chmod 644 logs/php-errors.log
```

### 7. Performance Optimization

#### Enable OPcache
Add to `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
```

#### Enable Gzip Compression
Already in `.htaccess` - verify it works

#### Database Indexes
Verify indexes exist on:
- `songs.uploaded_by`
- `songs.album_id`
- `songs.status`
- `users.email`
- `users.username`
- `follows.follower_id`
- `follows.following_id`

### 8. Backup Strategy

#### Database Backup Script
Create `backup-database.php`:
```php
<?php
$backup_file = 'backups/db_backup_' . date('Y-m-d_H-i-s') . '.sql';
$command = "mysqldump -u music_prod -p'password' music_streaming > $backup_file";
exec($command);
```

#### Automated Backups
Set up cron job:
```bash
0 2 * * * /usr/bin/php /path/to/backup-database.php
```

### 9. Monitoring

#### Error Monitoring
- Set up error log monitoring
- Configure alerts for critical errors

#### Uptime Monitoring
- Use services like UptimeRobot, Pingdom
- Set up alerts for downtime

#### Performance Monitoring
- Use tools like New Relic, Datadog
- Monitor slow queries
- Track page load times

### 10. Testing Checklist

#### Before Going Live:
- [ ] Test all user registration/login flows
- [ ] Test file uploads (audio, images)
- [ ] Test song playback
- [ ] Test search functionality
- [ ] Test artist profiles
- [ ] Test album creation/editing
- [ ] Test playlist creation
- [ ] Test favorites functionality
- [ ] Test on mobile devices
- [ ] Test on different browsers
- [ ] Test with slow internet connection
- [ ] Test error handling (404, 500 pages)
- [ ] Test security (SQL injection, XSS)
- [ ] Test file permissions
- [ ] Test database backups/restore

### 11. Post-Launch Monitoring

#### First Week:
- Monitor error logs daily
- Check server resources (CPU, memory, disk)
- Monitor database performance
- Review access logs for suspicious activity
- Check user feedback

#### Regular Maintenance:
- Daily: Check error logs
- Weekly: Review security logs
- Monthly: Update dependencies
- Quarterly: Security audit

## Quick Commands

### Generate Encryption Key
```bash
php -r "echo bin2hex(random_bytes(32));"
```

### Check File Permissions
```bash
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod 755 uploads/ logs/
```

### Test Database Connection
```bash
php -r "require 'config/database.php'; \$db = new Database(); \$conn = \$db->getConnection(); echo 'Connected!';"
```

### Check PHP Settings
```bash
php -i | grep -E "display_errors|error_reporting|upload_max_filesize"
```

## Security Checklist

- [ ] Encryption key changed
- [ ] Database credentials updated
- [ ] Environment set to production
- [ ] Error display disabled
- [ ] Debug files removed/protected
- [ ] SSL certificate installed
- [ ] HTTPS enforced
- [ ] Security headers configured (.htaccess)
- [ ] File permissions set correctly
- [ ] Sensitive directories protected
- [ ] Session security enabled
- [ ] Input validation working
- [ ] SQL injection protection (PDO prepared statements)
- [ ] XSS protection (htmlspecialchars)
- [ ] File upload validation working
- [ ] Rate limiting implemented (if needed)

## Support Resources

- **Error Logs**: `logs/php-errors.log`
- **Access Logs**: Server access logs (location depends on hosting)
- **Database Logs**: MySQL error log
- **Configuration**: `config/config.php`
- **Security**: `.htaccess`





