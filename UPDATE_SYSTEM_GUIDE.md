# Automatic Update System Guide

## Overview

The platform includes a WordPress-style automatic update system that allows clients to install updates with one click - no manual download or extraction needed.

## How It Works

### For Clients (One-Click Installation)

1. **Check for Updates**
   - Go to: Admin Panel → Settings → Check Updates
   - System automatically checks license server for new versions
   - Shows update details if available

2. **Install Update**
   - Click "Install Update Automatically" button
   - System automatically:
     - Creates backup of current files
     - Downloads update ZIP from license server
     - Extracts update files
     - Installs/replaces files
     - Updates version number
     - Cleans up temporary files

3. **Progress Display**
   - Real-time progress bar
   - Step-by-step status updates
   - Detailed log of operations
   - Automatic rollback on failure

### For You (License Server Admin)

1. **Create Update Package**
   - Prepare your update as a ZIP file
   - Include all files that need to be updated
   - Upload to your server or hosting

2. **Add Update in License Server**
   - Go to: License Server → Updates
   - Click "Create New Update"
   - Fill in:
     - Version number (e.g., 1.1.0)
     - Title and description
     - Changelog
     - **Download URL** (link to ZIP file)
   - Mark as "Critical" if it's a security patch
   - Save update

3. **Notify Clients**
   - Click "Notify" button next to update
   - All active license holders will be notified
   - Clients will see update in their admin panel

## Update Package Structure

Your update ZIP should contain files in the same structure as the main platform:

```
update-v1.1.0.zip
├── admin/
│   └── new-file.php
├── config/
│   └── updated-config.php
├── index.php (updated)
└── ...
```

**Important:**
- Include only files that need to be updated
- Maintain the same directory structure
- Don't include backups/, updates/, temp/, or uploads/ folders
- Don't include config/config.php (contains sensitive data)

## Security Features

1. **Backup Before Update**
   - Automatic backup created before installation
   - Stored in `backups/` directory
   - Can be restored if update fails

2. **Rollback Capability**
   - If update fails, system offers rollback
   - Restores from latest backup automatically
   - Ensures platform remains functional

3. **Permission Checks**
   - Only super admin can install updates
   - License verification required
   - Secure file operations

## File Exclusions

The update system automatically excludes:
- `backups/` - Backup files
- `updates/` - Update files
- `temp/` - Temporary files
- `uploads/` - User uploads
- `node_modules/` - Dependencies
- `.git/` - Version control

## Update Process Steps

1. **Backup** (10%)
   - Creates ZIP backup of all files
   - Excludes unnecessary directories
   - Stores in `backups/` folder

2. **Download** (30%)
   - Downloads update ZIP from license server
   - Validates file size and integrity
   - Stores in `updates/` folder

3. **Extract** (50%)
   - Extracts ZIP to temporary directory
   - Prepares files for installation

4. **Install** (70%)
   - Copies/replaces files from update
   - Maintains directory structure
   - Preserves permissions

5. **Finalize** (100%)
   - Updates version in database
   - Cleans up temporary files
   - Completes installation

## Troubleshooting

### Update Fails During Download
- Check download URL is accessible
- Verify ZIP file is not corrupted
- Check server has write permissions

### Update Fails During Installation
- Check file permissions
- Verify disk space available
- Check PHP memory limit

### Files Not Updating
- Ensure update ZIP has correct structure
- Check file permissions on target directory
- Verify files are not locked by another process

### Rollback Required
- System offers automatic rollback on failure
- Manual rollback: Restore from `backups/` directory
- Backup files are timestamped for easy identification

## Best Practices

1. **Test Updates First**
   - Test update on development/staging environment
   - Verify all functionality works
   - Check for breaking changes

2. **Version Numbering**
   - Use semantic versioning (MAJOR.MINOR.PATCH)
   - Example: 1.0.0 → 1.0.1 (patch) → 1.1.0 (minor) → 2.0.0 (major)

3. **Update Package Size**
   - Keep updates focused (only changed files)
   - Large updates may timeout
   - Consider multiple smaller updates for major changes

4. **Critical Updates**
   - Mark security patches as "Critical"
   - Clients will see prominent warning
   - Encourage immediate installation

5. **Communication**
   - Use changelog to explain changes
   - Provide clear instructions if manual steps needed
   - Notify clients about breaking changes

## API Endpoints

### Check Updates
- **URL:** `{LICENSE_SERVER}/api/updates.php?version={CURRENT_VERSION}`
- **Method:** GET
- **Returns:** Update information and availability

### Client Update Installer
- **File:** `admin/install-update.php`
- **Actions:** backup, download, extract, install, finalize, rollback
- **Progress:** Real-time via AJAX

## License Integration

- License is verified during installation
- License key stored in config during setup
- Updates require valid license
- License server tracks update installations

## Backup Management

- Backups stored in `backups/` directory
- Format: `backup_YYYY-MM-DD_HH-MM-SS.zip`
- Keep last 5 backups (auto-cleanup recommended)
- Can restore manually if needed

## Notes

- Updates preserve user data (database not affected)
- Uploads and user files are not touched
- Config files are preserved (except version)
- Custom modifications may need manual merge


