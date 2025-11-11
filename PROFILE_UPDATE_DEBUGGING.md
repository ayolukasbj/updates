# Profile Update Issue - Debugging Guide

## Problem:
User reports: "profile fields still not updated after savings, still returns empty fields after saving"

---

## ✅ What I've Implemented:

### 1. **Comprehensive Error Logging**

Added detailed logging at every step:

```php
// Before UPDATE
error_log("Attempting to update profile for user_id: $user_id");
error_log("Data: username=$username, bio=$bio, ...");

// During UPDATE
error_log("Executing UPDATE with/without avatar. Params: ...");
error_log("UPDATE successful. Rows affected: $rowCount");

// Verification
error_log("Verification query result: ...");

// After fetch
error_log("User data fetched: username=..., bio=...");
```

**Where to find logs:**
- Windows XAMPP: `C:\xampp\apache\logs\error.log`
- Or: `C:\xampp\php\logs\php_error.log`
- Or check `php.ini` for `error_log` setting

### 2. **Database Verification Step**

After UPDATE, immediately query the database to verify data was saved:

```php
// Verify the data was actually saved
$verify_stmt = $conn->prepare("SELECT username, bio, facebook, twitter, instagram, youtube FROM users WHERE id = ?");
$verify_stmt->execute([$user_id]);
$saved_data = $verify_stmt->fetch(PDO::FETCH_ASSOC);
error_log("Verification query result: " . print_r($saved_data, true));
```

### 3. **Diagnostic Tools Created**

#### A. **profile-diagnostic.php**
- Shows exactly what's in the database for the logged-in user
- Displays all user fields
- Shows recent error logs
- **Usage:** Navigate to `http://localhost/music/profile-diagnostic.php`

#### B. **test-profile-update.php**
- Simple test form to isolate the update issue
- Shows immediate before/after comparison
- Displays rows affected
- **Usage:** Navigate to `http://localhost/music/test-profile-update.php`

---

## 🔍 Diagnostic Steps:

### Step 1: Check Current Database State

1. Visit: `http://localhost/music/profile-diagnostic.php`
2. Look at "User Data from Database" section
3. Check if fields have values or are NULL/empty

**Expected Result:**
- Username should have a value
- Other fields may be empty (that's OK for new users)

**If fields are NULL:**
- Database schema may be missing columns
- Need to check `users` table structure

### Step 2: Test Simple Update

1. Visit: `http://localhost/music/test-profile-update.php`
2. Enter test data in username and bio
3. Click "Test Update"
4. Check:
   - "Execute result" should be TRUE
   - "Rows affected" should be 1
   - "Verification (immediate)" should show your new data

**If "Rows affected" is 0:**
- User ID mismatch
- WHERE clause not matching any row

**If "Execute result" is FALSE:**
- SQL error
- Database connection issue
- Column doesn't exist

### Step 3: Check Error Logs

**Windows/XAMPP locations:**
```
C:\xampp\apache\logs\error.log
C:\xampp\php\logs\php_error.log
```

**Look for lines containing:**
- "Attempting to update profile"
- "UPDATE successful"
- "Verification query result"
- "User data fetched"

**What to check:**
1. Is the UPDATE executing?
2. What data is being saved?
3. What data is being fetched back?
4. Are they the same?

### Step 4: Check Database Schema

Run this SQL query in phpMyAdmin:

```sql
DESCRIBE users;
```

**Required columns:**
- `id` (INT, PRIMARY KEY)
- `username` (VARCHAR)
- `bio` (TEXT or VARCHAR)
- `facebook` (VARCHAR)
- `twitter` (VARCHAR)
- `instagram` (VARCHAR)
- `youtube` (VARCHAR)
- `avatar` (VARCHAR)

**If any column is missing:**
```sql
ALTER TABLE users ADD COLUMN bio TEXT;
ALTER TABLE users ADD COLUMN facebook VARCHAR(255);
ALTER TABLE users ADD COLUMN twitter VARCHAR(255);
ALTER TABLE users ADD COLUMN instagram VARCHAR(255);
ALTER TABLE users ADD COLUMN youtube VARCHAR(255);
```

---

## 🐛 Possible Issues & Solutions:

### Issue 1: Fields Don't Exist in Database

**Symptoms:**
- SQL error in logs
- Error: "Unknown column 'bio' in 'field list'"

**Solution:**
```sql
ALTER TABLE users 
ADD COLUMN bio TEXT,
ADD COLUMN facebook VARCHAR(255),
ADD COLUMN twitter VARCHAR(255),
ADD COLUMN instagram VARCHAR(255),
ADD COLUMN youtube VARCHAR(255);
```

### Issue 2: Data Saving But Not Loading

**Symptoms:**
- "Rows affected: 1" in test
- Verification shows correct data
- But form fields still empty

**Possible Causes:**
- Caching (already fixed with cache headers)
- Wrong user ID being fetched
- JavaScript resetting fields

**Solution:**
Check error logs for:
```
User data fetched: username=XXX, bio=YYY
```

If this shows NULL, the fetch query is the problem.

### Issue 3: User ID Mismatch

**Symptoms:**
- Rows affected: 0
- Data not updating

**Causes:**
- Session user ID doesn't match database user ID
- User logged in with different account

**Check:**
```php
error_log("Session user_id: " . $_SESSION['user_id']);
error_log("Updating user_id: " . $user_id);
```

### Issue 4: Transaction Not Committing

**Symptoms:**
- UPDATE runs without error
- Data appears in verification
- But disappears after redirect

**Causes:**
- PDO in transaction mode
- No commit before redirect
- Connection closes before commit

**Already Fixed:**
```php
if ($conn->inTransaction()) {
    $conn->commit();
}
usleep(100000); // 0.1 second delay
```

### Issue 5: Empty String vs NULL

**Symptoms:**
- Fields show as empty in form
- But data is actually in database

**Cause:**
- Form expecting value but getting NULL

**Check form fields:**
```php
// This should work:
<input value="<?php echo htmlspecialchars($user['bio'] ?? ''); ?>">

// This would fail if bio is NULL:
<input value="<?php echo htmlspecialchars($user['bio']); ?>">
```

**Already Fixed:** All fields use `?? ''` fallback

---

## 📋 Verification Checklist:

### Database Check:
- [ ] All required columns exist in `users` table
- [ ] `bio`, `facebook`, `twitter`, `instagram`, `youtube` columns present
- [ ] Columns allow NULL or empty strings
- [ ] No unique constraints causing conflicts

### Code Check:
- [ ] UPDATE query executes without errors
- [ ] Rows affected is > 0
- [ ] Verification query returns updated data
- [ ] User data fetch query gets correct user
- [ ] Form fields use `$user['field'] ?? ''` syntax

### Logging Check:
- [ ] Error logs show "Attempting to update profile"
- [ ] Logs show "UPDATE successful. Rows affected: 1"
- [ ] Logs show verification data with correct values
- [ ] Logs show fetched data with correct values

### Cache Check:
- [ ] Cache-Control headers present
- [ ] Redirect URL has unique parameters
- [ ] No browser caching of page
- [ ] Hard refresh (Ctrl+F5) shows updated data

---

## 🎯 Next Steps for User:

### 1. **Immediate Actions:**

```bash
# 1. Visit diagnostic page
http://localhost/music/profile-diagnostic.php

# 2. Visit test update page
http://localhost/music/test-profile-update.php

# 3. Check error logs
C:\xampp\apache\logs\error.log
C:\xampp\php\logs\php_error.log
```

### 2. **Test Sequence:**

1. Open `test-profile-update.php`
2. Note current username and bio
3. Change both fields
4. Click "Test Update"
5. Observe:
   - Execute result (should be TRUE)
   - Rows affected (should be 1)
   - Verification data (should show new values)
6. Refresh page
7. Check if "Current Data in Database" shows new values

### 3. **Report Back:**

Please provide:

1. **Screenshot of `profile-diagnostic.php`** showing:
   - User data from database section
   - Specific fields section

2. **Screenshot of `test-profile-update.php`** showing:
   - Result after clicking "Test Update"
   - Particularly: Execute result, Rows affected, Verification

3. **Error Log Excerpt:**
   - Last 100 lines from error.log
   - Search for "update profile" (case insensitive)
   - Copy any errors or relevant messages

4. **Database Schema:**
   - Run `DESCRIBE users;` in phpMyAdmin
   - Screenshot of result

---

## 🔧 Temporary Workaround:

If form fields appear empty but database has data:

### Option A: Manual Field Population

Edit `artist-profile-mobile.php` around line 960:

```php
<!-- Add this debugging block -->
<div style="background: yellow; padding: 10px; margin: 10px 0;">
    DEBUG: 
    <?php 
    echo "username=" . htmlspecialchars($user['username'] ?? 'UNDEFINED');
    echo ", bio=" . htmlspecialchars($user['bio'] ?? 'UNDEFINED');
    ?>
</div>

<!-- Then your form fields -->
<input type="text" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
```

This will show if `$user` array has the data.

### Option B: Direct Database Check

Add at top of form (temporary):

```php
<?php
// Temporary debug
$debug_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$debug_stmt->execute([$user_id]);
$debug_user = $debug_stmt->fetch(PDO::FETCH_ASSOC);
echo "<pre>DEBUG USER DATA: ";
print_r($debug_user);
echo "</pre>";
?>
```

---

## 📝 Summary of Changes Made:

### Files Modified:
1. ✅ `artist-profile-mobile.php`
   - Added comprehensive error logging
   - Added database verification after UPDATE
   - Added 0.1s delay before redirect
   - Added explicit transaction commit
   - Added detailed parameter logging

### Files Created:
1. ✅ `profile-diagnostic.php` - Full diagnostic tool
2. ✅ `test-profile-update.php` - Simple update test
3. ✅ `PROFILE_UPDATE_DEBUGGING.md` - This guide

### Logging Added:
- Before update attempt
- During UPDATE execution
- After UPDATE (with row count)
- Verification query results
- After user data fetch
- Error cases with full details

---

## 🎓 Understanding the Issue:

### Normal Flow:
```
User submits form
  ↓
POST data received
  ↓
UPDATE query executed
  ↓
Database commits changes
  ↓
Redirect to GET request
  ↓
Fetch fresh user data
  ↓
Display in form fields ✅
```

### Problem Flow (What might be happening):
```
User submits form
  ↓
POST data received
  ↓
UPDATE query executed
  ↓
??? Something fails here ???
  ↓
Redirect to GET request
  ↓
Fetch user data (unchanged)
  ↓
Display old/empty data in form ❌
```

### What We Need to Find:
- At which step does it fail?
- Does UPDATE execute?
- Does it affect rows?
- Does verification show correct data?
- Does fetch get different data?

**The detailed logging will reveal exactly where the problem occurs.**

---

## 🆘 If Still Not Working:

### Last Resort - Manual SQL Update:

1. Open phpMyAdmin
2. Select your music database
3. Click on `users` table
4. Find your user row
5. Click "Edit"
6. Manually enter bio, social links
7. Save
8. Refresh `artist-profile-mobile.php`
9. Check if manual changes appear

**If manual changes don't appear:**
- Problem is with data fetching, not saving

**If manual changes appear:**
- Problem is with the UPDATE query

---

**Created:** October 30, 2025
**Purpose:** Debug profile update issue
**Status:** Awaiting user diagnostic results

