# 🚀 Quick Start: Profile & Artist Features

## ⚡ 3-Step Setup

### Step 1: Update Database (ONE-TIME ONLY)
Visit this URL in your browser:
```
http://localhost/music/admin/update-schema.php
```
This will:
- ✅ Add avatar, cover_image, social_links columns
- ✅ Create upload directories
- ✅ Set up verification system

### Step 2: Edit User Profiles
Users can now edit their profiles at:
```
http://localhost/music/profile-edit.php
```

**Features Available:**
- Upload profile picture
- Upload cover image
- Add social media links (6 platforms)
- Update bio

### Step 3: Manage Artists (Admin Only)
Admin can edit artists at:
```
http://localhost/music/admin/artists.php
```
Click "View" on any artist, then edit to add:
- Profile picture
- Cover image
- Social media links
- **Verification badge** (toggle on/off)

---

## 🎯 Quick Actions

### ✅ Verify an Artist
1. Go to Admin > Artists
2. Click "View" on artist
3. Toggle "Verification Status" switch ON
4. Click "Save Changes"
5. ✨ Blue checkmark appears on frontend!

### 📷 Upload Artist Images
1. Edit artist in admin panel
2. Click "Upload Avatar" button
3. Select image (will preview instantly)
4. Click "Upload Cover" for cover image
5. Click "Save Changes"

### 🔗 Add Social Links
1. Edit artist or user profile
2. Scroll to "Social Media Links" section
3. Paste URLs for each platform
4. Save changes

---

## 📍 Key URLs

| Page | URL | Purpose |
|------|-----|---------|
| Database Setup | `/admin/update-schema.php` | ONE-TIME: Add columns |
| User Profile Edit | `/profile-edit.php` | Users edit their profiles |
| Admin Artist Edit | `/admin/artist-edit.php?id=X` | Edit artist details |
| Artist List (Frontend) | `/artistes.php` | Shows verification badges |
| Admin Artist List | `/admin/artists.php` | Manage all artists |

---

## 🎨 Verification Badge

**What it looks like:**
- Blue circle badge in top-right corner of artist card
- Blue checkmark icon (✓) next to artist name
- Color: #2196F3 (Primary blue)

**Where it appears:**
- Frontend artist list (`artistes.php`)
- Artist profile pages
- Search results

---

## 📁 Upload Directories

Images are stored in:
```
uploads/
  ├── avatars/     (profile pictures)
  ├── covers/      (cover images)
  ├── audio/       (song files)
  └── images/      (other images)
```

**Important:** Make sure these directories have write permissions (777)

---

## 🔧 Troubleshooting

### ❌ "Column not found" error
**Solution:** Run `/admin/update-schema.php`

### ❌ Image upload fails
**Solution:** Check directory permissions:
```bash
chmod -R 777 uploads/
```

### ❌ Verification badge not showing
**Solution:** 
1. Check artist is marked as verified in database
2. Clear browser cache
3. Ensure `artistes.php` is updated

### ❌ Social links not saving
**Solution:** Database needs `social_links` column. Run update-schema.php

---

## ✨ Features Summary

### User Profiles:
- ✅ Avatar upload
- ✅ Cover image upload
- ✅ Bio/description
- ✅ 6 social media platforms
- ✅ Email & username update

### Artist Management:
- ✅ Everything from user profiles, plus:
- ✅ Verification status toggle
- ✅ Play/download statistics
- ✅ Professional artist pages

### Frontend Display:
- ✅ Verification badges on artist cards
- ✅ Blue checkmark icons
- ✅ Responsive design
- ✅ Professional appearance

---

## 📞 Support

If you encounter issues:
1. Check `PROFILE_SOCIAL_UPDATE.md` for detailed documentation
2. Verify database columns exist (run update-schema.php)
3. Check file permissions on upload directories
4. Clear browser cache after updates

---

**Last Updated:** <?php echo date('Y-m-d H:i:s'); ?>

