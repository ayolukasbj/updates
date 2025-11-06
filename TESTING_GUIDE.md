# Testing Guide - Installation Process

## Prerequisites

Before testing, ensure you have:

1. **XAMPP/WAMP running** with:
   - Apache running
   - MySQL running

2. **License Server Setup:**
   - License server should be accessible at `https://hylinktech.com/server`
   - Or for local testing, update the license server URL in `install.php` to `http://localhost/license-server`
   - Ensure license server database is set up
   - Have at least one test license created

3. **Test License:**
   - Create a test license in the license server admin panel
   - Note the license key
   - Note the domain/IP it's bound to

## Testing Steps

### Step 1: Prepare for Installation

1. **Delete existing config** (if testing re-installation):
   ```bash
   # Delete or rename config/config.php
   # Or set SITE_INSTALLED = false in config.php
   ```

2. **Clear database** (optional, for fresh install):
   - Drop the database or use a new database name
   - Or delete tables manually from phpMyAdmin

### Step 2: Access Installation Wizard

1. Open browser
2. Navigate to: `http://localhost/music/install.php`
3. You should see the installation wizard with Step 1

### Step 3: Test License Verification

1. **Enter License Key:**
   - Use the license key from your license server
   - Format: `XXXX-XXXX-XXXX-XXXX-XXXX`

2. **Enter Domain:**
   - Use `localhost` or your actual domain
   - Must match the domain in license server

3. **Click "Verify License & Continue"**
   - Should verify with license server
   - If valid, proceed to Step 2
   - If invalid, show error message

**Test Cases:**
- ✅ Valid license key + correct domain = Success
- ❌ Invalid license key = Error message
- ❌ Valid license key + wrong domain = Error message
- ❌ Network error (server down) = Error message

### Step 4: Test Site Configuration

1. **Fill in Site Information:**
   - Site Name: `Test Music Platform`
   - Site Slogan: `Test Slogan`
   - Site Description: `Test description`
   
2. **Fill in Admin Account:**
   - Username: `admin` (or any username)
   - Email: `admin@test.com`
   - Password: `Test123!@#` (must be 8+ chars)
   - Confirm Password: `Test123!@#`

3. **Click "Continue to Database Setup"**
   - Should validate all fields
   - If valid, proceed to Step 3
   - If invalid, show specific error

**Test Cases:**
- ✅ All fields filled correctly = Success
- ❌ Missing site name = Error
- ❌ Invalid email = Error
- ❌ Password too short = Error
- ❌ Passwords don't match = Error

### Step 5: Test Database Configuration

1. **Enter Database Details:**
   - Database Host: `localhost`
   - Database Name: `music_test` (or any name)
   - Database Username: `root`
   - Database Password: (leave empty for XAMPP default)

2. **Click "Test Connection & Continue"**
   - Should test database connection
   - Should create database if it doesn't exist
   - If successful, proceed to Step 4

**Test Cases:**
- ✅ Valid credentials = Success
- ❌ Wrong password = Connection error
- ❌ Wrong host = Connection error
- ✅ New database name = Creates database

### Step 6: Test Installation Process

1. **Step 4 should show:**
   - "Installing..." message
   - Loading spinner
   - Auto-redirect to installation

2. **Installation should:**
   - Create all database tables
   - Insert site settings
   - Create admin user
   - Generate config file
   - Complete successfully

3. **Step 5 should show:**
   - "Installation Complete!" message
   - Links to admin panel and homepage

**Test Cases:**
- ✅ All tables created = Success
- ✅ Settings saved to database = Success
- ✅ Admin user created = Success
- ✅ Config file generated = Success
- ❌ Database error = Error message

### Step 7: Test Post-Installation

1. **Access Admin Panel:**
   - URL: `http://localhost/music/admin/login.php`
   - Login with admin credentials from Step 4
   - Should login successfully

2. **Check Settings:**
   - Go to: Admin Panel → Settings → General
   - Should see site name, slogan, etc. from installation
   - Should be able to edit and save

3. **Verify Database:**
   - Open phpMyAdmin
   - Check `settings` table has values
   - Check `users` table has admin user
   - Check all other tables exist

4. **Check Config File:**
   - Open `config/config.php`
   - Should have `SITE_INSTALLED = true`
   - Should have all constants defined
   - Should have license key

## Local Testing (License Server)

If testing locally with license server:

1. **Update License Server URL in install.php:**
   ```php
   $license_server_url = 'http://localhost/license-server';
   $license_api_url = 'http://localhost/license-server/api/verify.php';
   ```

2. **Ensure License Server is Running:**
   - Access: `http://localhost/license-server`
   - Login with admin credentials
   - Create a test license
   - Note the license key

3. **Test License Verification:**
   - Use the test license key
   - Domain should match your local setup

## Troubleshooting Tests

### Test 1: Invalid License
- Enter wrong license key
- Expected: Error message, installation blocked

### Test 2: License Server Down
- Stop license server
- Try to verify license
- Expected: Connection error, installation blocked

### Test 3: Database Already Exists
- Use existing database name
- Expected: Should work, tables added if missing

### Test 4: Re-installation Protection
- After installation, try to access `install.php` again
- Expected: "Already Installed" message

### Test 5: Settings Management
- Change site name in admin panel
- Check if it updates throughout site
- Expected: Changes reflect everywhere

## Quick Test Checklist

- [ ] Installation wizard loads
- [ ] License verification works
- [ ] Invalid license is rejected
- [ ] Site configuration form validates
- [ ] Database connection works
- [ ] Database creation works
- [ ] All tables are created
- [ ] Settings are saved
- [ ] Admin user is created
- [ ] Config file is generated
- [ ] Installation complete message shows
- [ ] Admin login works
- [ ] Settings page loads
- [ ] Settings can be edited

## Test Data

**Recommended Test License:**
- License Key: Create in license server
- Domain: `localhost`
- Type: `standard`
- Status: `active`

**Recommended Test Admin:**
- Username: `testadmin`
- Email: `test@example.com`
- Password: `Test123!@#`

**Recommended Test Database:**
- Name: `music_test`
- Host: `localhost`
- User: `root`
- Password: (empty for XAMPP)

## Expected Results

After successful installation:
- ✅ Database has all tables
- ✅ `settings` table has site configuration
- ✅ `users` table has admin user
- ✅ `config/config.php` exists and is valid
- ✅ Admin panel is accessible
- ✅ Settings page shows installed values
- ✅ Site name appears in admin panel
- ✅ License key is stored in config

## Notes

- For local testing, you may need to update license server URL
- Ensure license server database is accessible
- Test with different license types if available
- Test with expired/invalid licenses
- Test with missing database
- Test with wrong database credentials


