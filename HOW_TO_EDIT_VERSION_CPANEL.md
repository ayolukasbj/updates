# How to Edit Platform Version from cPanel

## Method 1: Using cPanel File Manager

1. **Login to cPanel**
   - Go to your cPanel login page
   - Enter your username and password

2. **Open File Manager**
   - Navigate to "Files" section
   - Click on "File Manager"

3. **Navigate to Version File**
   - Go to your website's root directory (usually `public_html` or `www`)
   - Navigate to: `config/version.php`

4. **Edit the File**
   - Right-click on `version.php`
   - Select "Edit" or "Code Edit"
   - Change the version number:
     ```php
     define('SCRIPT_VERSION', '1.0');  // Change '1.0' to your desired version
     ```
   - Example: `define('SCRIPT_VERSION', '1.1');`
   - Click "Save Changes"

## Method 2: Using cPanel Database (phpMyAdmin)

1. **Open phpMyAdmin**
   - In cPanel, go to "Databases" section
   - Click on "phpMyAdmin"

2. **Select Your Database**
   - Click on your database name (usually `music_streaming` or similar)

3. **Edit Settings Table**
   - Click on `settings` table
   - Click "Browse" tab
   - Find the row where `setting_key` = `script_version`
   - Click "Edit" (pencil icon)
   - Change the `setting_value` to your desired version (e.g., `1.1`)
   - Click "Go" to save

## Method 3: Using SSH/Terminal (Advanced)

1. **Connect via SSH**
   - Use SSH client (PuTTY, Terminal, etc.)
   - Connect to your server

2. **Navigate to Project Directory**
   ```bash
   cd public_html
   cd config
   ```

3. **Edit version.php**
   ```bash
   nano version.php
   ```
   - Change the version number
   - Press `Ctrl+X` to exit
   - Press `Y` to save
   - Press `Enter` to confirm

## Method 4: Using FTP Client

1. **Connect via FTP**
   - Use FTP client (FileZilla, WinSCP, etc.)
   - Connect to your server

2. **Download version.php**
   - Navigate to `config/version.php`
   - Download the file

3. **Edit Locally**
   - Open in text editor
   - Change version number
   - Save the file

4. **Upload Back**
   - Upload the edited file back to `config/version.php`
   - Overwrite the existing file

## Important Notes

- **Version Format**: Use semantic versioning (e.g., `1.0`, `1.1`, `2.0`)
- **Database vs File**: The database `settings` table takes precedence over the file
- **Both Locations**: It's recommended to update both:
  - `config/version.php` (file)
  - `settings` table in database (setting_key = `script_version`)

## Verification

After updating the version:
1. Go to: `https://yourdomain.com/admin/check-updates.php`
2. Check if the current version is displayed correctly
3. The version should match what you set

## Troubleshooting

- **Permission Error**: Make sure file permissions are `644` or `755`
- **Not Updating**: Clear browser cache and refresh
- **Database Error**: Make sure `settings` table exists and has the `script_version` row

