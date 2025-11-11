# Database Tables Sync Guide

## Problem
- Localhost has 35 database tables
- Live server has only 25 tables
- Missing 10 tables causing functionality issues

## Solution

### Step 1: Upload Sync Script
1. Upload `sync-database-tables.php` to your live server
2. Access: `https://tesotalents.com/sync-database-tables.php`

### Step 2: Run the Script
1. The script will:
   - Check existing tables
   - Identify missing tables
   - Create missing tables automatically
   - Verify all tables exist

### Step 3: Verify
1. Check the final table count (should be 35)
2. Test all functionality
3. Delete the sync script after use

## Tables That Will Be Created

### Core Tables (23):
1. users
2. artists
3. genres
4. albums
5. songs
6. playlists
7. playlist_songs
8. user_favorites
9. downloads
10. play_history
11. subscriptions
12. payments
13. reviews
14. follows
15. notifications
16. settings
17. news
18. news_comments
19. news_views
20. admin_logs
21. song_comments
22. song_ratings

### Additional Tables (12):
23. email_settings
24. email_templates
25. email_queue
26. news_categories
27. song_collaborators
28. favorites (if needed)
29. user_playlists (if needed)
30. artist_social_links (if needed)
31. song_lyrics (if needed)
32. biography
33. albums_songs
34. license_activations

## What the Script Does

1. **Connects to Database**
   - Uses existing config/database.php
   - Verifies connection

2. **Checks Existing Tables**
   - Lists all current tables
   - Compares with required tables

3. **Creates Missing Tables**
   - Creates each missing table with proper structure
   - Adds indexes and foreign keys
   - Inserts default data where needed

4. **Verifies Everything**
   - Re-runs core table creation
   - Ensures all tables exist
   - Shows final table count

## After Running

1. **Delete the Script**
   - Remove `sync-database-tables.php` for security

2. **Test Functionality**
   - Test homepage
   - Test artist profiles
   - Test login
   - Test all features

3. **Check Error Logs**
   - Verify no new errors
   - Monitor for issues

## Expected Result

- ✅ 35 tables in database
- ✅ All functionality working
- ✅ No missing table errors
- ✅ Homepage displays correctly
- ✅ Artist profiles work
- ✅ Login works

## Troubleshooting

If tables still missing:
1. Check database permissions
2. Check error logs
3. Run script again
4. Check for foreign key constraints

If errors occur:
1. Check database connection
2. Verify user has CREATE TABLE permission
3. Check for conflicting table names
4. Review error messages in script output

---

**Status:** Ready to sync database tables!












