# Advanced Settings Guide

## Overview
Comprehensive admin settings panel for managing every aspect of your music platform.

## Access
**URL:** `admin/settings-advanced.php`
**Menu:** Admin Panel → Advanced Settings

## Features

### 1. **General Settings** 🌐

#### Site Name
- Update the platform name
- Automatically updates `config.php`
- Reflects across the entire site

#### Site Tagline
- Short description of your platform
- Used in meta tags and headers

#### Show Site Name in Header
- Toggle to show/hide site name next to logo
- Useful if you want logo-only branding

#### Maintenance Mode
- Put site in maintenance mode
- Only admins can access when enabled
- Perfect for updates and maintenance

---

### 2. **Branding & Visual Identity** 🎨

#### Site Logo
- Upload custom logo
- Recommended: 200x60px
- Formats: PNG, JPG, SVG
- Replaces default music icon

#### Favicon
- Upload custom favicon
- Recommended: 32x32px
- Formats: ICO, PNG
- Shows in browser tabs

#### Default Artist Cover Art
- Upload default cover for all artist profiles
- Recommended: 1200x400px
- Used when artist doesn't have custom cover
- Applies to ALL artist profiles automatically

---

### 3. **Email Configuration** 📧

Configure SMTP for sending emails (verification, notifications, etc.)

#### SMTP Settings
- **Host:** Mail server address (e.g., smtp.gmail.com)
- **Port:** Usually 587 (TLS) or 465 (SSL)
- **Username:** Your email address
- **Password:** Email password or app password

#### Email Display
- **From Email:** Sender email address
- **From Name:** Sender display name

---

### 4. **Social Media Links** 📱

Add your official social media profiles:
- Facebook
- Twitter/X
- Instagram
- YouTube
- TikTok

These links can be displayed in footer, about page, etc.

---

### 5. **Upload Settings** ⬆️

#### Max Upload Size
- Set maximum file size for uploads (MB)
- Range: 1-500MB
- Affects audio and image uploads

#### Allowed Audio Formats
- Comma-separated list
- Default: mp3,wav,ogg
- Controls what users can upload

#### Allowed Image Formats
- Comma-separated list
- Default: jpg,jpeg,png,gif
- For covers and avatars

#### Require Admin Approval
- Toggle to require admin approval for uploads
- New uploads won't be public until approved
- Great for content moderation

---

### 6. **SEO & Analytics** 🔍

#### Meta Description
- Site description for search engines
- Max: 160 characters
- Helps with Google rankings

#### Meta Keywords
- Comma-separated keywords
- Helps with SEO

#### Google Analytics ID
- Your GA tracking ID
- Format: G-XXXXXXXXXX
- Tracks site visitors

#### Facebook Pixel ID
- Your FB Pixel ID
- Format: 123456789012345
- Tracks conversions and ads

---

## How Settings Work

### Database Storage
All settings are stored in the `settings` table:
```sql
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP
)
```

### Auto-Creation
- Settings table is created automatically
- No manual database setup needed

### File Uploads
- Branding files saved to: `uploads/branding/`
- Automatically creates directory if missing
- Files renamed with timestamp

---

## Usage Examples

### Example 1: Complete Rebranding
1. Go to **Branding** tab
2. Upload new logo (your_logo.png)
3. Upload new favicon (favicon.ico)
4. Upload default artist cover (cover.jpg)
5. Go to **General** tab
6. Change "Site Name" to "Your Platform"
7. Set "Show Site Name" to ON
8. Save changes
9. **Result:** Complete platform rebrand!

### Example 2: Email Setup (Gmail)
1. Go to **Email** tab
2. Set:
   - SMTP Host: `smtp.gmail.com`
   - SMTP Port: `587`
   - Username: `yourEmail@gmail.com`
   - Password: [App Password](https://support.google.com/accounts/answer/185833)
   - From Email: `noreply@yoursite.com`
   - From Name: `Your Platform`
3. Save
4. **Result:** Email verification works!

### Example 3: Content Moderation
1. Go to **Uploads** tab
2. Enable "Require Admin Approval"
3. Set max upload size to 25MB
4. Set audio formats to "mp3,wav"
5. Save
6. **Result:** All uploads need approval!

---

## Important Features

### 🔄 Live Preview
- Logo/favicon uploads show instant preview
- No need to refresh page

### 💾 Auto-Save
- Each tab saves independently
- Changes take effect immediately
- No site restart needed

### 🛡️ Security
- Only admins can access
- Password encryption (base64)
- CSRF protection

### 📱 Mobile Responsive
- Works on all devices
- Touch-friendly toggles
- Scrollable tabs on mobile

---

## Troubleshooting

### Logo Not Showing?
1. Check file uploaded successfully
2. Clear browser cache (Ctrl+F5)
3. Verify file permissions (755)
4. Check `uploads/branding/` directory exists

### Emails Not Sending?
1. Verify SMTP credentials
2. Check port number (587 vs 465)
3. Enable "Less secure apps" (Gmail)
4. Use App Password instead of regular password

### Settings Not Saving?
1. Check database connection
2. Verify `settings` table exists
3. Check write permissions
4. View PHP error logs

---

## What Gets Applied Where

### Site Name
- Header logo text
- Page titles (`<title>`)
- Email "From Name"
- Footer copyright

### Logo
- Header (replaces icon)
- Login/Register pages
- Email templates
- Admin panel

### Default Artist Cover
- Artist profile pages (when no custom cover)
- Artist cards/listings
- Song details artist section

### Social Links
- Footer social icons
- About page
- Artist profiles (optional)

---

## Best Practices

1. **Logo:** Use transparent PNG for best results
2. **Favicon:** Use 32x32 ICO or PNG
3. **Covers:** Use high-res images (1200px+ width)
4. **SMTP:** Always use app passwords, not regular passwords
5. **SEO:** Update meta description to be compelling
6. **Upload Limits:** Balance quality vs storage
7. **Approval:** Enable for public platforms, disable for private

---

## Advanced Tips

### Custom Artist Covers
1. Upload default cover in **Branding**
2. Artists can upload custom covers in profile
3. Default shows until custom is uploaded
4. All artists get consistent look

### Maintenance Mode
1. Enable before major updates
2. Prevents user access during changes
3. Admins can still access
4. Shows maintenance page to visitors

### Brand Consistency
1. Use same colors in logo and favicon
2. Match social media branding
3. Keep artist covers themed
4. Update site name across all platforms

---

## Security Notes

- ⚠️ **NEVER** share SMTP password
- 🔒 Use strong admin passwords
- 🛡️ Keep PHP updated
- 📁 Set proper file permissions (755)
- 🔐 Enable HTTPS for production

---

## Support

If you encounter issues:
1. Check error logs: `admin/debug.php`
2. Verify database: `admin/check-db.php`
3. Test settings individually
4. Contact admin support

---

**Last Updated:** October 30, 2025
**Version:** 1.0

