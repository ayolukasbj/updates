# Pre-Production Checklist

## Critical Issues to Fix Before Going Live

### 1. Security Issues ⚠️

#### Configuration Security
- [ ] **ENCRYPTION_KEY**: Currently set to default `'your-secret-key-here-change-this'` in `config/config.php`
  - **Action**: Generate a strong random key and update it
  - **Command**: `php -r "echo bin2hex(random_bytes(32));"`

- [ ] **Database Credentials**: Currently using default (root/no password)
  - **Action**: Create a dedicated database user with limited privileges
  - **Action**: Use strong password and update `config/config.php`

- [ ] **API Keys Placeholders**: 
  - SMTP_PASSWORD: `'your-app-password'`
  - PAYPAL_CLIENT_ID/SECRET: `'your-paypal-client-id'`
  - STRIPE_KEYS: `'your-stripe-*'`
  - **Action**: Update with real credentials or remove if not needed

#### Error Display
- [ ] **song-details.php**: Has `error_reporting(E_ALL)` and `display_errors = 1` enabled
  - **Action**: Remove or wrap in environment check
  - **Line**: 4-5 in song-details.php

### 2. Debug/Test Files 🗑️

#### Files to Remove or Move:
- [ ] `debug-*.php` files (9 files found)
- [ ] `test-*.php` files (17 files found)
- [ ] `*-backup.php` files
- [ ] `*-working.php`, `*-simple.php`, `*-debug.php` variants

**Action**: Either delete these files or move to a protected directory outside web root

### 3. Environment Configuration 🌍

#### Missing Environment Variable
- [ ] **ENVIRONMENT constant**: Not defined in `config/config.php`
  - **Action**: Add `define('ENVIRONMENT', 'production');` or use `.env` file
  - **Current**: Config checks for `ENVIRONMENT === 'development'` but it's never defined

#### Production Settings
- [ ] **Error Reporting**: Currently defaults to `error_reporting(0)` when ENVIRONMENT !== 'development'
  - **Status**: ✅ Good - but ensure ENVIRONMENT is set

- [ ] **Logging**: Ensure error logging is enabled and log directory is writable
  - **Action**: Check `logs/` directory permissions

### 4. File Permissions 🔒

#### Directories to Check:
- [ ] `uploads/` - Should be writable (755 or 775)
- [ ] `logs/` - Should be writable (755 or 775)
- [ ] `config/` - Should NOT be web-accessible (outside web root ideally)
- [ ] `database/` - Should NOT be web-accessible

### 5. Database Optimization 📊

#### Indexes and Performance
- [ ] Check database indexes exist for frequently queried columns
- [ ] Review slow queries and optimize
- [ ] Ensure proper foreign key constraints

### 6. Security Headers 🔐

#### Missing Headers
- [ ] **Content Security Policy (CSP)**
- [ ] **X-Frame-Options**: Prevent clickjacking
- [ ] **X-Content-Type-Options**: Prevent MIME sniffing
- [ ] **Strict-Transport-Security**: If using HTTPS

### 7. File Upload Security 📤

#### Validation
- [ ] Verify file type validation is working
- [ ] Check file size limits
- [ ] Ensure uploaded files are scanned for malware
- [ ] Verify file paths can't be manipulated (directory traversal)

### 8. Session Security 🔑

#### Session Configuration
- [ ] **Session cookie settings**: HttpOnly, Secure (if HTTPS), SameSite
- [ ] **Session regeneration**: On login/privilege changes
- [ ] **Session timeout**: Currently 1 hour - verify appropriate

### 9. SQL Injection & XSS Protection 🛡️

#### Current Status
- [ ] **PDO Prepared Statements**: ✅ Most queries use prepared statements
- [ ] **htmlspecialchars()**: ✅ Output is escaped
- [ ] **Input Sanitization**: ✅ `sanitize_input()` function exists
- [ ] **Review**: Check all user inputs are properly sanitized

### 10. Rate Limiting ⏱️

#### Missing Features
- [ ] **API Rate Limiting**: Prevent abuse
- [ ] **Login Attempt Limits**: Prevent brute force
- [ ] **Upload Rate Limiting**: Prevent spam uploads

### 11. Backup & Recovery 💾

#### Backup Strategy
- [ ] **Database Backups**: Automated daily backups
- [ ] **File Backups**: Uploads directory backup strategy
- [ ] **Recovery Plan**: Test restore process

### 12. Monitoring & Logging 📈

#### Setup Required
- [ ] **Error Logging**: Configure and monitor
- [ ] **Access Logs**: Review regularly
- [ ] **Performance Monitoring**: Track slow pages
- [ ] **Uptime Monitoring**: Set up alerts

### 13. SSL/HTTPS 🔒

#### SSL Configuration
- [ ] **SSL Certificate**: Install and configure
- [ ] **Force HTTPS**: Redirect all HTTP to HTTPS
- [ ] **Mixed Content**: Ensure all resources load over HTTPS

### 14. Performance Optimization ⚡

#### Optimization
- [ ] **Caching**: Enable opcode cache (OPcache)
- [ ] **CDN**: Consider for static assets
- [ ] **Image Optimization**: Compress images
- [ ] **Database Query Optimization**: Review N+1 queries
- [ ] **Minify CSS/JS**: For production

### 15. Testing ✅

#### Testing Checklist
- [ ] **Functionality Testing**: All features work
- [ ] **Security Testing**: Penetration testing
- [ ] **Performance Testing**: Load testing
- [ ] **Browser Compatibility**: Test on major browsers
- [ ] **Mobile Responsiveness**: Test on mobile devices

### 16. Documentation 📚

#### Documentation
- [ ] **Installation Guide**: For server setup
- [ ] **Configuration Guide**: How to configure settings
- [ ] **User Guide**: For end users
- [ ] **Admin Guide**: For administrators

### 17. Legal & Compliance ⚖️

#### Compliance
- [ ] **Privacy Policy**: Must be present
- [ ] **Terms of Service**: Must be present
- [ ] **Cookie Policy**: If using cookies
- [ ] **GDPR Compliance**: If serving EU users
- [ ] **DMCA Policy**: For copyright compliance

## Quick Fixes to Apply Now

1. **Fix song-details.php error display**
2. **Define ENVIRONMENT constant**
3. **Update ENCRYPTION_KEY**
4. **Remove or protect debug/test files**
5. **Add security headers**
6. **Update database credentials**





