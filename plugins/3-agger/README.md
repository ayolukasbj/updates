# MP3 Tagger Plugin

A standalone plugin for the Music Platform that provides professional MP3 ID3 tag editing capabilities.

## Features

- **ID3 Tag Settings**: Configure automatic tagging templates
- **Sync ID3 Tags**: Batch update all MP3 files with current settings
- **Edit MP3 Tags**: Edit individual MP3 file tags
- **Auto-tagging**: Automatically tag uploaded MP3 files
- **Site Branding**: Embed site logo and branding in MP3 files

## Installation

1. The plugin is located in `plugins/mp3-tagger/`
2. Go to **Admin Panel → Plugins**
3. Find "MP3 Tagger" in the list
4. Click **Activate**

## Requirements

- getID3 library (should be in `includes/getid3/`)
- PHP 7.4 or higher
- Write permissions for MP3 files

## Usage

### Settings
Configure tag templates and enable/disable auto-tagging:
- **Admin Panel → MP3 Tagger → Settings**

### Sync Tags
Update all existing MP3 files with current tag templates:
- **Admin Panel → MP3 Tagger → Sync Tags**

### Edit Tags
Edit individual MP3 file tags:
- **Admin Panel → MP3 Tagger → Edit Tags**
- Or click the "Tags" button next to any song in the Songs list

## Plugin Structure

```
plugins/mp3-tagger/
├── mp3-tagger.php          # Main plugin file
├── includes/
│   ├── class-mp3-tagger.php    # MP3Tagger class
│   └── class-auto-tagger.php   # AutoTagger class
├── admin/
│   ├── mp3-tagger.php      # Admin router
│   ├── settings.php        # Settings page
│   ├── sync.php           # Sync page
│   └── edit.php           # Edit page
└── README.md              # This file
```

## Integration

The plugin integrates with:
- Upload system (auto-tags on upload)
- Songs management (quick tag access)
- Admin navigation (appears in menu when active)

## Deactivation

When deactivated:
- Auto-tagging stops working
- MP3 Tagger menu disappears
- Original `admin/mp3-tagger.php` can be used as fallback

## Notes

- This plugin replaces the standalone `admin/mp3-tagger.php` when active
- All functionality is preserved from the original implementation
- Can be managed remotely via License Server Platform Management

