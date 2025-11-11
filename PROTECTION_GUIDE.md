# Platform Protection Guide

This guide explains how to protect your platform from nulling and unauthorized reselling.

## Overview

The license protection system includes:
- License key generation and validation
- Domain/IP binding
- Server-side verification
- Grace period for new installations
- Anti-tampering measures

## Setup Instructions

### 1. Enable License Protection

Edit `config/config.php` and set:
```php
define('ENVIRONMENT', 'production');
```

### 2. Enable Automatic License Checks

Edit `config/license.php` and uncomment the last line:
```php
verifyPlatformLicense(); // Uncomment this line
```

### 3. Generate License Keys

1. Go to `/admin/license-management.php`
2. Create licenses for your customers
3. Each license is bound to a specific domain/IP

## Protection Features

### Domain Binding
- Each license is tied to a specific domain
- License cannot be used on different domains
- Prevents sharing licenses between sites

### IP Binding (Optional)
- Can bind license to server IP
- Prevents moving license to different servers

### Grace Period
- New installations get 7 days grace period
- Allows testing before activation
- After 7 days, license is required

### License Types
- **Trial**: Limited time access
- **Standard**: Regular subscription
- **Premium**: Enhanced features
- **Lifetime**: No expiration

## Security Best Practices

### 1. Encrypt License Keys
- License keys are stored encrypted in database
- Use strong encryption key in `config.php`

### 2. Server-Side Validation
- Enable remote license server (optional)
- Set `LICENSE_SERVER_URL` in config
- Creates additional validation layer

### 3. Code Obfuscation
- Obfuscate PHP files before distribution
- Use tools like PHP Obfuscator or Zend Guard
- Makes nulling attempts harder

### 4. File Integrity Checks
- Add checksums to critical files
- Verify files haven't been modified
- Detect tampering attempts

### 5. Database Protection
- Use strong database credentials
- Restrict database access
- Regular backups

## License Management

### Creating Licenses
1. Login as Super Admin
2. Go to License Management
3. Click "Create New License"
4. Fill customer details
5. Generate license key
6. Send key to customer

### Activating Licenses
1. Customer receives license key
2. Goes to `/admin/license-management.php`
3. Enters license key
4. System validates and binds to domain

### Monitoring
- View all licenses in admin panel
- See last verification time
- Track verification counts
- Monitor expiration dates

## Anti-Nulling Measures

### 1. Code Integrity
- Check critical files for modifications
- Verify license.php hasn't been altered
- Compare file checksums

### 2. Multiple Check Points
- License check in multiple files
- Not just in config/license.php
- Add checks in critical functions

### 3. Obfuscation
- Obfuscate license verification code
- Make it harder to bypass
- Use eval() with encoded strings (advanced)

### 4. Remote Validation
- Use license server for validation
- Cannot be bypassed locally
- Server controls all licenses

### 5. Time-Based Checks
- Periodic license verification
- Not just on page load
- Random verification intervals

## Additional Protection Steps

### 1. Remove Comments
- Remove all code comments
- No hints about protection logic
- Clean code before distribution

### 2. Minify Code
- Minify JavaScript/CSS
- Remove whitespace
- Harder to read and modify

### 3. Use Constants
- Store sensitive data in constants
- Encrypt configuration values
- Don't hardcode license keys

### 4. Add Watermarks
- Add hidden identifiers in code
- Track which copy was nulled
- Help identify source

### 5. Legal Protection
- Include license agreement
- Terms of service
- Legal consequences for violation

## Testing License System

### Test Activation
1. Create test license
2. Activate on test domain
3. Verify binding works
4. Test expiration

### Test Violations
1. Try using license on different domain
2. Verify it fails
3. Test expired license
4. Verify blocking

## Troubleshooting

### License Not Activating
- Check license key format
- Verify license is active in database
- Check domain binding
- Review error logs

### False Positives
- Check ENVIRONMENT setting
- Verify grace period hasn't expired
- Check database connection
- Review license status

## Support

For issues or questions:
1. Check error logs
2. Review license status
3. Verify database tables exist
4. Check ENVIRONMENT setting

## Important Notes

- **Never commit license.php to public repositories**
- **Keep encryption keys secret**
- **Regularly backup license database**
- **Monitor license usage**
- **Update protection regularly**

## Advanced: License Server Setup

If you want remote validation:

1. Create license server API
2. Endpoint: `/api/verify`
3. Accepts: license_key, domain, ip
4. Returns: valid/invalid status
5. Set `LICENSE_SERVER_URL` in config

This adds an extra layer of protection that cannot be bypassed locally.


