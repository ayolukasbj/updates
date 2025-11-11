# Installation Guide

## Overview

This platform includes a comprehensive installation wizard that verifies your license, configures all settings, and sets up the database automatically.

## License Server Configuration

- **License Server URL:** `https://hylinktech.com/server`
- **API Endpoint:** `https://hylinktech.com/api/verify.php`

## Installation Steps

### Step 1: Access Installation Wizard

Navigate to: `http://your-domain.com/music/install.php`

### Step 2: License Verification

1. Enter your **License Key** (received from license server)
2. Enter your **Domain** (where the platform will be installed)
3. Click "Verify License & Continue"
4. The system will verify your license with the license server
5. If verification fails, installation will be refused

### Step 3: Site Configuration

After license verification, configure:

- **Site Name** (required)
- **Site Slogan** (optional)
- **Site Description** (optional)
- **Admin Username** (required)
- **Admin Email** (required)
- **Admin Password** (required, minimum 8 characters)
- **Confirm Password** (required)

### Step 4: Database Configuration

Enter your database credentials:

- **Database Host** (usually `localhost`)
- **Database Name** (will be created if it doesn't exist)
- **Database Username** (usually `root` for XAMPP)
- **Database Password** (leave empty if no password)

The installation will:
- Create the database if it doesn't exist
- Import all required tables from `database/schema.sql`
- Create the admin user account
- Save all settings to the database

### Step 5: Installation Complete

After successful installation:
- You'll be redirected to the admin panel or homepage
- The `config/config.php` file will be created automatically
- All settings are stored in the database
- License information is stored and verified

## Post-Installation

### Access Admin Panel

1. Go to: `http://your-domain.com/music/admin/login.php`
2. Login with your admin credentials
3. Configure additional settings from **Settings → General**

### Manage Site Settings

All site settings can be managed from:
- **Admin Panel → Settings → General**
- Settings include:
  - Site Name
  - Site Slogan
  - Site Description
  - Site Logo
  - Favicon

## License Verification

The platform automatically verifies your license:
- During installation
- Periodically during runtime (configurable)
- License server URL: `https://hylinktech.com/server`
- API endpoint: `https://hylinktech.com/api/verify.php`

## Troubleshooting

### Installation Fails

1. **Check Database Credentials**
   - Ensure MySQL is running
   - Verify database user has CREATE DATABASE privileges
   - Check XAMPP/WAMP services are running

2. **License Verification Fails**
   - Verify license key is correct
   - Ensure domain matches the license domain
   - Check internet connection to license server
   - Verify license server is accessible

3. **Database Schema Import Fails**
   - Ensure `database/schema.sql` exists
   - Check database user has CREATE TABLE privileges
   - Review error messages for specific table issues

### Re-installation

To reinstall:
1. Delete `config/config.php` file
2. Or set `SITE_INSTALLED = false` in config file
3. Access `install.php` again

## Security Notes

- Change default admin password immediately
- Use strong passwords (8+ characters, mixed case, numbers, symbols)
- Keep license server URL secure
- Don't share license keys publicly
- Regularly backup database

## License Server Requirements

The license server must:
- Be accessible at `https://hylinktech.com/server`
- Have API endpoint at `https://hylinktech.com/api/verify.php`
- Return JSON response with `valid` field
- Include license details in response

## Support

For installation issues:
1. Check error messages in installation wizard
2. Review database connection settings
3. Verify license server connectivity
4. Check server error logs


