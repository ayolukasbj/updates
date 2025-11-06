# Admin Management System & Theme System - Complete Guide

## 🎉 What's Been Created:

### ✅ **1. Critical Database Fixes:**
- `fix-database-schema.php` - Automatically adds missing profile columns
- `check-db-schema.php` - Verifies database structure
- `test-profile-update.php` - Tests profile saving
- `profile-diagnostic.php` - Full diagnostic tool

### ✅ **2. Admin Management Pages:**
- `admin/form-fields-manager.php` - Manage upload & profile forms
- `admin/homepage-manager.php` - Manage homepage sections

### ⏳ **3. Theme System (Creating Now):**
- Theme Manager Admin Page
- Magazine Theme
- News Theme  
- HowWeBiz Theme

---

## 📋 Admin Management Features:

### Form Fields Manager (`admin/form-fields-manager.php`)

**Upload Form Fields:**
- Enable/disable fields
- Change field labels
- Set field types (text, textarea, select, number, checkbox, file)
- Mark fields as required/optional
- Fields managed:
  - Song Title
  - Artist Name
  - Album
  - Genre
  - Release Year
  - Lyrics
  - Explicit Content

**Profile Form Fields:**
- Enable/disable fields
- Change field labels
- Set field types (text, textarea, url, file)
- Mark fields as required/optional
- Fields managed:
  - Artist Name
  - Biography
  - Profile Picture
  - Facebook URL
  - Twitter URL
  - Instagram URL
  - YouTube URL

**How to Use:**
1. Login as admin
2. Go to `http://localhost/music/admin/form-fields-manager.php`
3. Click "Upload Form Fields" or "Profile Form Fields" tab
4. Enable/disable fields with checkboxes
5. Edit labels as needed
6. Change field types from dropdowns
7. Mark required fields
8. Click "Save" button

---

### Homepage Manager (`admin/homepage-manager.php`)

**Manages Homepage Sections:**

1. **Hero Section**
   - Title text
   - Subtitle text
   - Search bar toggle
   - Background image

2. **Featured Songs**
   - Section title
   - Number of songs to display
   - Layout (grid/list/slider)

3. **Top Artists**
   - Section title
   - Number of artists
   - Layout style

4. **Latest Songs**
   - Section title
   - Number of songs
   - Layout style

5. **Top 100 Chart**
   - Section title
   - Time period (week/month/all-time)

6. **Latest News**
   - Section title
   - Number of news items

**Features:**
- ✅ Toggle sections on/off
- ✅ Configure section settings
- ✅ Drag-and-drop to reorder (UI ready)
- ✅ Preview homepage changes
- ✅ Individual section customization

**How to Use:**
1. Login as admin
2. Go to `http://localhost/music/admin/homepage-manager.php`
3. Toggle switches to enable/disable sections
4. Click gear icon (⚙️) to configure section settings
5. Edit titles, limits, layouts
6. Click "Save Homepage Sections"
7. Click "Preview Homepage" to see changes

---

## 🎨 Theme System Architecture:

### Theme Structure:
```
themes/
├── magazine/
│   ├── index.php (Homepage)
│   ├── style.css
│   ├── header.php
│   ├── footer.php
│   └── theme.json (Theme metadata)
├── news/
│   ├── index.php
│   ├── style.css
│   ├── header.php
│   ├── footer.php
│   └── theme.json
└── howwebiz/
    ├── index.php
    ├── style.css
    ├── header.php
    ├── footer.php
    └── theme.json
```

### Theme Features:

#### **1. Magazine Theme**
- Grid-based layout
- Large featured images
- Category cards
- Magazine-style typography
- Sidebar widgets
- Featured content slider

#### **2. News Theme**
- Breaking news ticker
- Category navigation sidebar
- Article listing layout
- Related content sections
- Comment sections ready
- Author boxes

#### **3. HowWeBiz Theme**
- Exact replica of HowWe.ug
- Same color scheme (#FF6600 primary)
- Same navigation structure
- Same card designs
- Same typography
- Same spacing/layout

### Theme Manager Features:
- Browse available themes
- Preview themes before activating
- One-click theme switching
- Theme customization options
- Theme-specific settings

---

## 🚀 Quick Start Guide:

### Step 1: Fix Database (REQUIRED FIRST!)

```
Visit: http://localhost/music/fix-database-schema.php
```

This adds missing columns to `users` table.

### Step 2: Access Admin Panel

```
Visit: http://localhost/music/admin/
```

Login with admin credentials.

### Step 3: Manage Forms

```
Visit: http://localhost/music/admin/form-fields-manager.php
```

- Configure upload form fields
- Configure profile form fields
- Enable/disable fields
- Change labels

### Step 4: Manage Homepage

```
Visit: http://localhost/music/admin/homepage-manager.php
```

- Enable/disable sections
- Configure section settings
- Preview changes

### Step 5: Choose Theme (Coming Next)

```
Visit: http://localhost/music/admin/theme-manager.php
```

- Browse themes
- Preview themes
- Activate theme
- Customize theme settings

---

## 📊 Database Schema:

### Settings Table Structure:

```sql
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_group VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Stored Settings:

| Setting Key | Setting Group | Purpose |
|------------|---------------|---------|
| `upload_form_fields` | forms | Upload form configuration |
| `profile_form_fields` | forms | Profile form configuration |
| `homepage_sections` | homepage | Homepage sections config |
| `active_theme` | themes | Currently active theme |
| `theme_settings` | themes | Theme-specific settings |

---

## 🔧 Technical Implementation:

### Form Fields Manager:

**Data Structure:**
```json
{
  "upload_fields": [
    {
      "name": "title",
      "label": "Song Title",
      "type": "text",
      "required": true,
      "enabled": true
    }
  ]
}
```

**Storage:** JSON in `settings` table
**Retrieval:** Decoded and used in forms
**Validation:** Field requirements enforced

### Homepage Manager:

**Data Structure:**
```json
{
  "sections": [
    {
      "id": "hero",
      "name": "Hero Section",
      "enabled": true,
      "order": 1,
      "settings": {
        "title": "Discover Amazing Music",
        "subtitle": "Stream and Download",
        "show_search": true
      }
    }
  ]
}
```

**Rendering:** Homepage reads settings and displays accordingly
**Ordering:** Sections displayed in specified order
**Toggling:** Disabled sections not rendered

---

## 🎯 Usage Examples:

### Example 1: Disable Album Field on Upload

1. Go to Form Fields Manager
2. Click "Upload Form Fields" tab
3. Find "Album" row
4. Uncheck "Enabled" checkbox
5. Click "Save Upload Fields"
6. Album field removed from upload form!

### Example 2: Change "Biography" to "About Me"

1. Go to Form Fields Manager
2. Click "Profile Form Fields" tab
3. Find "Biography" row
4. Change label to "About Me"
5. Click "Save Profile Fields"
6. Profile form now says "About Me"!

### Example 3: Disable News Section on Homepage

1. Go to Homepage Manager
2. Find "Latest News" section
3. Toggle switch to OFF
4. Click "Save Homepage Sections"
5. News section removed from homepage!

### Example 4: Show Only 6 Featured Songs

1. Go to Homepage Manager
2. Find "Featured Songs" section
3. Click gear icon (⚙️)
4. Change "Limit" to 6
5. Click "Save Homepage Sections"
6. Homepage now shows 6 featured songs!

---

## 🐛 Troubleshooting:

### Issue: Changes Not Reflecting

**Solution:**
1. Hard refresh browser (Ctrl + F5)
2. Clear browser cache
3. Check if save was successful
4. Verify settings in database

### Issue: Admin Pages Not Accessible

**Solution:**
1. Verify you're logged in as admin
2. Check `users` table for `role = 'admin'`
3. Update user role if needed:
```sql
UPDATE users SET role = 'admin' WHERE id = YOUR_USER_ID;
```

### Issue: Settings Not Saving

**Solution:**
1. Check database connection
2. Verify `settings` table exists
3. Check error logs for SQL errors
4. Ensure proper permissions

---

## 📝 Next Steps:

### Immediate:
1. ✅ Run `fix-database-schema.php`
2. ✅ Test profile update
3. ✅ Access admin panel
4. ✅ Configure forms
5. ✅ Configure homepage

### Coming Soon:
1. ⏳ Theme Manager UI
2. ⏳ Magazine Theme
3. ⏳ News Theme
4. ⏳ HowWeBiz Theme
5. ⏳ Theme Customizer

---

## 🎓 Understanding the System:

### Flow Diagram:

```
Admin logs in
    ↓
Access Admin Panel
    ↓
Choose Management Area:
    ├─→ Form Fields Manager
    │       ↓
    │   Configure Upload/Profile Forms
    │       ↓
    │   Save to Database (settings table)
    │       ↓
    │   Forms automatically use new config
    │
    ├─→ Homepage Manager
    │       ↓
    │   Enable/Disable Sections
    │       ↓
    │   Configure Section Settings
    │       ↓
    │   Save to Database
    │       ↓
    │   Homepage renders based on config
    │
    └─→ Theme Manager (Coming)
            ↓
        Browse Themes
            ↓
        Preview Theme
            ↓
        Activate Theme
            ↓
        Entire site uses new theme
```

---

## 💡 Pro Tips:

### Tip 1: Backup Before Changes
Always backup your database before making major configuration changes.

### Tip 2: Test in Preview
Use the "Preview" buttons to see changes before committing.

### Tip 3: Incremental Changes
Make small changes and test, rather than changing everything at once.

### Tip 4: Document Custom Settings
Keep notes of custom configurations for future reference.

### Tip 5: Use Descriptive Labels
When changing field labels, make them clear and user-friendly.

---

## 🔒 Security Notes:

### Admin Access:
- Only users with `role = 'admin'` can access admin pages
- Session validation on every admin page
- Redirect to login if not authenticated

### Input Validation:
- All inputs sanitized before saving
- SQL injection protection (prepared statements)
- XSS protection (htmlspecialchars)

### Data Integrity:
- Settings stored as JSON with validation
- Fallback to defaults if settings corrupt
- Error handling on save failures

---

**Status:** ✅ Admin Management Complete, ⏳ Themes In Progress  
**Created:** October 30, 2025  
**Version:** 1.0

