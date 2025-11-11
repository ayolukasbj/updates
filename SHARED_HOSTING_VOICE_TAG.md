# Voice Tag Feature for Shared Hosting

## Overview

The voice tag feature now supports **shared hosting environments** without requiring FFmpeg installation. The system automatically falls back to a simple concatenation method when FFmpeg is not available.

## How It Works

### Method 1: FFmpeg (Best Quality)
- **When Available**: If FFmpeg is installed on your server
- **Supports**: MP3, WAV, M4A, AAC, and other formats
- **Quality**: High-quality audio processing with proper format conversion
- **Best For**: VPS, dedicated servers, or shared hosting with FFmpeg support

### Method 2: Simple Concatenation (Shared Hosting Fallback)
- **When Available**: Automatically used when FFmpeg is not found
- **Supports**: MP3 files only (both song and voice tag must be MP3)
- **Quality**: Basic concatenation - works best with files of the same bitrate
- **Best For**: Shared hosting without FFmpeg access

## Requirements for Simple Concatenation

1. **Both files must be MP3 format**
   - The song file must be `.mp3`
   - The voice tag file must be `.mp3`

2. **Same bitrate recommended**
   - Works best when both files have the same bitrate (e.g., 128kbps, 192kbps, 320kbps)
   - Different bitrates may work but could cause slight audio quality issues

3. **File size limits**
   - Keep voice tags small (under 5MB recommended)
   - Larger files may take longer to process

## How to Use

1. **Navigate to MP3 Tagger**
   - Go to Admin → Songs
   - Click the "Tags" button (📋) next to any song

2. **Add Voice Tag**
   - Scroll down to the "Add Voice Tag" section
   - Select position (Start or End)
   - Upload an MP3 voice tag file (or select existing)
   - Click "Add Voice Tag to Song"

3. **System Behavior**
   - If FFmpeg is available: Uses FFmpeg for processing
   - If FFmpeg is not available: Automatically uses simple concatenation
   - A backup of the original file is created automatically

## Limitations of Simple Concatenation

1. **MP3 Only**: Only works with MP3 files
2. **No Format Conversion**: Cannot convert between audio formats
3. **Bitrate Matching**: Works best with matching bitrates
4. **No Audio Processing**: No normalization, volume adjustment, or effects

## Tips for Best Results

1. **Prepare Voice Tags**
   - Export voice tags as MP3 format
   - Use the same bitrate as your songs (if possible)
   - Keep voice tags short (5-10 seconds recommended)

2. **Test First**
   - Try with a test song first
   - Check the output quality
   - Adjust voice tag format if needed

3. **Backup Files**
   - The system creates automatic backups
   - Backups are saved as: `original_file.backup.timestamp`
   - Keep backups until you verify the result

## Troubleshooting

### "Cannot process: Both files must be MP3 format"
- **Solution**: Convert your voice tag to MP3 format first
- Use an online converter or audio editing software

### "Failed to process voice tag"
- **Solution**: 
  - Ensure both files are valid MP3 files
  - Check file permissions (files must be readable/writable)
  - Try with smaller files first

### Audio Quality Issues
- **Solution**: 
  - Ensure both files have the same bitrate
  - Re-export voice tag with matching bitrate
  - Consider using FFmpeg if available (contact hosting provider)

## Alternative Solutions

If simple concatenation doesn't work for your needs:

1. **Contact Hosting Provider**
   - Ask if FFmpeg can be installed
   - Some shared hosting providers offer FFmpeg support

2. **Use VPS/Dedicated Server**
   - Full control over server environment
   - Can install FFmpeg and other tools

3. **Cloud Processing**
   - Use external API services (Cloudinary, AWS Lambda, etc.)
   - Process files via cloud functions
   - Requires API integration (not yet implemented)

## File Locations

- **Voice Tags**: `uploads/voice-tags/`
- **Backups**: Same directory as original song file (with `.backup.timestamp` extension)
- **Processed Files**: Replace original files (backups are created first)

## Support

If you encounter issues:
1. Check error logs in your hosting control panel
2. Verify file permissions
3. Ensure both files are valid MP3 format
4. Try with smaller test files first

