# Fix Upload to Database Issue

## 🚨 Problem:
**"Song uploaded and saved to JSON (database unavailable)"**

This means the database INSERT is failing, so the system falls back to JSON storage.

---

## ✅ Solution: Run Database Fix Script

### **STEP 1: Fix Songs Table**

Visit: **`http://localhost/music/fix-songs-table.php`**

This will:
- ✅ Create the `songs` table if it doesn't exist
- ✅ Add any missing columns
- ✅ Verify the table structure
- ✅ Test database connection

**Expected Result:**
```
✓ Songs table created successfully!
OR
✓ Added column: [column_name]
...
Songs table is now ready!
Current songs in database: 0
```

---

### **STEP 2: Also Fix Users Table**

Visit: **`http://localhost/music/fix-database-schema.php`**

This will:
- ✅ Add missing profile columns (`bio`, `facebook`, `twitter`, etc.)
- ✅ Verify users table structure

---

### **STEP 3: Test Upload**

1. Go to `upload.php`
2. Upload a test song
3. Check for success message: **"Song uploaded successfully!"**
4. Go to `artist-profile-mobile.php?tab=music`
5. Verify song appears in list

---

## 🔍 Diagnostic Steps:

### Check Error Logs:

**Location:** `C:\xampp\apache\logs\error.log`

**Look for:**
```
CRITICAL: Song upload failed - [error message]
SQL State: [error code]
```

Common errors and solutions:

#### Error: "Table 'songs' doesn't exist"
**Fix:** Run `fix-songs-table.php`

#### Error: "Unknown column 'uploaded_by' in 'field list'"
**Fix:** Run `fix-songs-table.php` to add missing columns

#### Error: "Can't connect to database"
**Fix:** 
1. Check if MySQL is running in XAMPP
2. Verify database credentials in `config/database.php`
3. Create database if it doesn't exist

#### Error: "Access denied for user"
**Fix:** Check database credentials in `config/database.php`

---

## 📊 Required Database Structure:

### Songs Table Must Have:

```sql
CREATE TABLE songs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    artist_id INT DEFAULT NULL,
    album_title VARCHAR(255) DEFAULT NULL,
    genre VARCHAR(100) DEFAULT NULL,
    release_year INT DEFAULT NULL,
    file_path VARCHAR(500) NOT NULL,
    cover_art VARCHAR(500) DEFAULT NULL,
    duration INT DEFAULT NULL,
    file_size BIGINT DEFAULT NULL,
    lyrics TEXT DEFAULT NULL,
    is_explicit TINYINT(1) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'active',
    is_featured TINYINT(1) DEFAULT 0,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT NOT NULL,
    plays INT DEFAULT 0,
    downloads INT DEFAULT 0
);
```

### Users Table Must Have:

```sql
ALTER TABLE users ADD COLUMN bio TEXT DEFAULT NULL;
ALTER TABLE users ADD COLUMN facebook VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN twitter VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN instagram VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN youtube VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL;
```

---

## 🛠️ Manual Database Check:

### Option 1: Via phpMyAdmin

1. Open `http://localhost/phpmyadmin`
2. Select your music database
3. Click on `songs` table
4. Click "Structure" tab
5. Verify all columns exist

### Option 2: Via SQL Query

```sql
DESCRIBE songs;
DESCRIBE users;
```

---

## 🎯 Quick Fix Checklist:

- [ ] Run `fix-songs-table.php`
- [ ] Verify "Songs table is now ready!" message
- [ ] Run `fix-database-schema.php`
- [ ] Verify all columns added
- [ ] Test upload a song
- [ ] Check for "Song uploaded successfully!" (not JSON message)
- [ ] Go to music tab
- [ ] Verify song appears
- [ ] Check song count in database

---

## 📝 After Fix Verification:

### Check Songs in Database:

```sql
SELECT id, title, uploaded_by, upload_date 
FROM songs 
ORDER BY upload_date DESC 
LIMIT 10;
```

Should show your uploaded songs!

### Check Songs in Music Tab:

Visit: `http://localhost/music/artist-profile-mobile.php?tab=music&debug=1`

Look for:
```
DEBUG INFO:
Total songs found: 1 (or more)
User ID: YOUR_ID
First song: YOUR_SONG_TITLE
```

---

## ⚠️ Common Issues:

### Issue: Still Saving to JSON After Fix

**Possible Causes:**
1. Browser cached the error
2. PHP opcache not cleared
3. Database credentials wrong

**Solutions:**
1. Hard refresh: Ctrl + F5
2. Restart Apache in XAMPP
3. Check `config/database.php` for correct credentials
4. Verify MySQL service is running

### Issue: "Database connection error"

**Check:**
1. Is MySQL running in XAMPP Control Panel?
2. Start MySQL if it's stopped
3. Check if database exists:
   ```sql
   SHOW DATABASES LIKE 'music';
   ```
4. Create database if missing:
   ```sql
   CREATE DATABASE music CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

### Issue: Songs Not Appearing After Upload

**Debug:**
1. Check error logs
2. Use debug mode: `?tab=music&debug=1`
3. Verify `uploaded_by` matches your user ID
4. Run SQL query to check database:
   ```sql
   SELECT * FROM songs WHERE uploaded_by = YOUR_USER_ID;
   ```

---

## 🔄 Upload Flow (After Fix):

```
User uploads song
    ↓
Files saved to server
    ↓
Database INSERT executed
    ↓
✅ SUCCESS: Song saved to database
    ↓
JSON backup also saved
    ↓
Redirect to music tab
    ↓
Song appears in list
```

**Instead of:**
```
User uploads song
    ↓
Files saved to server
    ↓
Database INSERT fails ❌
    ↓
Catch exception
    ↓
Fallback to JSON only
    ↓
Message: "saved to JSON (database unavailable)"
```

---

## 📞 Still Having Issues?

### Check These:

1. **XAMPP Services:**
   - Apache: Running ✓
   - MySQL: Running ✓

2. **Database Config:**
   - File: `config/database.php`
   - DB_HOST: Usually `localhost`
   - DB_NAME: Your database name
   - DB_USER: Usually `root`
   - DB_PASS: Usually empty for XAMPP

3. **Database Exists:**
   ```sql
   CREATE DATABASE IF NOT EXISTS music;
   USE music;
   ```

4. **Tables Exist:**
   ```sql
   SHOW TABLES;
   ```
   Should show: `songs`, `users`, `settings`, etc.

5. **Error Logs:**
   ```
   C:\xampp\apache\logs\error.log
   C:\xampp\php\logs\php_error.log
   ```

---

## 🎉 Success Indicators:

After running fixes, you should see:

✅ Upload message: **"Song uploaded successfully!"** (not "JSON")  
✅ Song appears in music tab immediately  
✅ Debug mode shows: "Total songs found: X"  
✅ Database query returns songs  
✅ Error logs show: "Song uploaded successfully! Song ID: X"

---

**🚀 START HERE:** `http://localhost/music/fix-songs-table.php`

Then test upload and verify songs save to database!

---

**Created:** October 30, 2025  
**Purpose:** Fix database upload issues  
**Status:** Ready to use

