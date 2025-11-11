# MP3 Tagger Setup Guide

## Overview
The MP3 Tagger allows you to edit ID3 tags directly in MP3 files and sync them with the database. This feature is integrated into the admin panel.

## Installation

### Step 1: Install getID3 Library

The MP3 tagger requires the `getID3` library. You have two options:

#### Option A: Using Composer (Recommended)
```bash
cd /path/to/your/music/platform
composer require james-heinrich/getid3
```

#### Option B: Manual Installation
1. Download getID3 from: https://github.com/JamesHeinrich/getID3
2. Extract the files
3. Place the `getid3` folder in your `includes/` directory or `vendor/` directory
4. The expected path is either:
   - `includes/getid3/getid3.php`
   - `vendor/getid3/getid3/getid3.php`

### Step 2: Verify Installation

After installation, access the MP3 Tagger from:
- Admin Panel → Songs → Click the "Tags" icon (green button with tag icon) next to any song

## Features

### What You Can Edit:
- **Title** - Song title (required)
- **Artist** - Artist name (required)
- **Album** - Album name
- **Year** - Release year
- **Genre** - Music genre
- **Track Number** - Track position in album
- **Album Art** - Upload and embed album artwork
- **Lyrics** - Embed lyrics in the MP3 file

### How It Works:
1. **Reads existing tags** from the MP3 file
2. **Displays current values** (database values take priority)
3. **Writes new tags** directly to the MP3 file
4. **Syncs with database** - Updates title, genre, track number, lyrics, and cover art

## Usage

### Accessing the MP3 Tagger:
1. Go to **Admin Panel → Songs**
2. Find the song you want to edit
3. Click the green **Tags** button (tag icon) next to the song
4. Edit the tags in the form
5. Click **Update MP3 Tags**

### Important Notes:
- **File Backup**: The tagger writes directly to MP3 files. Consider backing up files before editing.
- **File Format**: Currently optimized for MP3 files. Other formats may have limited support.
- **Album Art**: Uploaded album art is saved to `uploads/album-art/` and embedded in the MP3 file.
- **Database Sync**: Title, genre, track number, lyrics, and cover art are automatically synced with the database.

## Troubleshooting

### Error: "getID3 library not found"
- Make sure getID3 is installed in the correct location
- Check the paths in `includes/mp3-tagger.php`

### Error: "File not found"
- Verify the song's file path in the database
- Check file permissions (read/write access required)

### Tags not updating
- Check file permissions (must be writable)
- Verify the MP3 file is not corrupted
- Check error logs for detailed error messages

### Album art not displaying
- Ensure the image format is supported (JPG, PNG, GIF)
- Check file size (recommended max 2MB)
- Verify upload directory permissions

## Integration with Song Edit

The MP3 Tagger is separate from the Song Edit page but complements it:
- **Song Edit** (`song-edit.php`) - Edits database records
- **MP3 Tagger** (`mp3-tagger.php`) - Edits actual MP3 file tags

Both pages sync with each other for consistency.

## API Endpoint (Future Enhancement)

An API endpoint can be added for programmatic tag editing:
- `api/mp3-tagger.php` - For automated tag updates

## Security

- Only admin users can access the MP3 Tagger
- File uploads are validated (type and size)
- All inputs are sanitized before writing to files

## Support

For issues or questions:
- Check error logs: `logs/error.log`
- Verify getID3 installation
- Ensure PHP has read/write permissions for MP3 files

