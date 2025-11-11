# Artist Dashboard & Database Upload Update

## Summary of Changes

### 1. **Songs Now Save Directly to Database** ✅

**File:** `upload.php`

**Changes:**
- Songs are now saved to the `songs` table in MySQL database
- Artist records are created/updated in the `artists` table automatically
- User's profile picture is used as album art for all songs
- JSON backup is still maintained for redundancy
- **Auto-upgrade:** Users who upload songs are automatically upgraded to "artist" role

**Database Flow:**
1. Check if artist exists, create if not
2. Insert song with all metadata
3. Update user role to "artist" if they were a "user"
4. Also save to JSON as backup

### 2. **Separate Artist Dashboard Created** ✅

**Files Created:**
- `artist-dashboard.php` - Redirect handler
- `artist-profile-mobile.php` - Mobile-optimized artist backend (mdundo.com style)
- `artist-stats.php` - Artist statistics page
- `api/update-artist-status.php` - API for toggling active status

**File Updated:**
- `dashboard.php` - Now redirects artists to `artist-dashboard.php`

**Routing Logic:**
```
User logs in → dashboard.php checks role:
├─ Admin/Super Admin → admin/index.php
├─ Artist → artist-dashboard.php → artist-profile-mobile.php
└─ Regular User → stays on dashboard.php
```

### 3. **Artist Profile Mobile Features**

**Design:** Matches mdundo.com exactly

**Features:**
- ✅ Profile avatar (from database)
- ✅ Active/Inactive toggle switch (real-time AJAX update)
- ✅ Total downloads counter
- ✅ Artist ranking (calculated from database)
- ✅ "Upload song" button (links to upload.php)
- ✅ "Boost your music" button
- ✅ Bio display
- ✅ Navigation tabs (Profile, Music, News, Stats)
- ✅ Edit button (links to profile-edit.php)
- ✅ Public profile link
- ✅ Owner section

### 4. **Database Schema Used**

**Tables:**
- `songs` - Stores all song data
  - `title`, `artist_id`, `album_title`, `genre`, `release_year`
  - `file_path`, `cover_art`, `duration`, `file_size`, `lyrics`
  - `is_explicit`, `status`, `is_featured`, `upload_date`
  - `uploaded_by`, `plays`, `downloads`

- `artists` - Stores artist information
  - `name`, `avatar`, `bio`, `created_at`

- `users` - User accounts
  - `role` - Can be: 'user', 'artist', 'admin', 'super_admin'
  - Automatically upgraded to 'artist' when they upload first song

### 5. **Artist Collaboration Feature**

In `upload.php`:
- Artist name is auto-filled with logged-in user's username (readonly)
- "This is a collaboration" checkbox
- When checked, shows additional artists field
- Format: "YourName x OtherArtist1, OtherArtist2"
- Your name is always included and cannot be removed

### 6. **User Flow**

**For New Users:**
1. Register → Role: 'user'
2. Upload first song → Role automatically becomes: 'artist'
3. Login next time → Redirected to artist dashboard

**For Artists:**
1. Login → dashboard.php
2. Detected as artist → artist-dashboard.php
3. Redirected to → artist-profile-mobile.php
4. Can access:
   - Profile (artist-profile-mobile.php)
   - Music (my-songs.php)
   - News (news.php)
   - Stats (artist-stats.php)

**For Admins:**
1. Login → dashboard.php
2. Detected as admin → admin/index.php
3. Full admin panel access

### 7. **API Endpoints**

**New:**
- `api/update-artist-status.php`
  - Method: POST
  - Body: `{ "is_active": 0 or 1 }`
  - Updates user's active status in database

### 8. **Automatic Features**

1. **Role Upgrade:** First song upload → User becomes Artist
2. **Artist Creation:** New artist name → New artist record created
3. **Cover Art:** User's profile picture → Becomes album art for all songs
4. **Database First:** All saves go to database first, JSON as backup

### 9. **Error Handling**

- Database errors → Falls back to JSON save
- Missing columns → Gracefully handled
- Failed uploads → Clear error messages
- Missing artist → Automatically created

## Testing Checklist

- [ ] Upload a song as new user → Check role changes to 'artist'
- [ ] Upload collaboration song → Check artist format "User x Other"
- [ ] Check database for new song record
- [ ] Login as artist → Should see mobile artist profile
- [ ] Toggle active/inactive → Check database updates
- [ ] View stats page → Shows correct song metrics
- [ ] Upload with profile picture → Becomes album art
- [ ] View public profile → Shows updated artist data

## Files Modified/Created

**Modified:**
1. `upload.php` - Database saves, collaboration feature
2. `dashboard.php` - Artist redirect logic

**Created:**
1. `artist-dashboard.php` - Artist redirect handler
2. `artist-profile-mobile.php` - Mobile artist backend
3. `artist-stats.php` - Artist statistics
4. `api/update-artist-status.php` - Status toggle API
5. `ARTIST_DASHBOARD_UPDATE.md` - This documentation

## Notes

- Songs are saved to BOTH database and JSON (redundancy)
- Profile pictures are used as album art automatically
- Artists cannot remove their name from songs (only add collaborators)
- The dashboard routing is smart and role-based
- Mobile-first design matching mdundo.com

---

**Last Updated:** October 30, 2025
**Version:** 2.0

