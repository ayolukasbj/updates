# Quick Start Testing Guide

## 🚀 Fastest Way to Test Everything

### Option 1: Automated Test Scripts

1. **Test License Server API:**
   ```
   http://localhost/license-server/test-license-api.php?key=YOUR_LICENSE_KEY&domain=localhost
   ```

2. **Test Updates API:**
   ```
   http://localhost/license-server/test-updates-api.php?version=1.0.0
   ```

3. **Test Installation Flow:**
   ```
   http://localhost/music/test-installation-flow.php
   ```

4. **System Check:**
   ```
   http://localhost/music/test-installation.php
   ```

---

### Option 2: Manual Step-by-Step

#### 5-Minute Quick Test

**1. License Server (2 minutes)**
- Go to: `http://localhost/license-server/install.php`
- Install database
- Login: `admin` / `admin123`
- Create license (copy the key!)

**2. Client Installation (2 minutes)**
- Go to: `http://localhost/music/install.php`
- Enter license key from step 1
- Domain: `localhost`
- Fill site config (any test data)
- Database: `localhost`, `root`, (empty password)
- Complete installation

**3. Test Updates (1 minute)**
- Login to client admin
- Go to: Settings → Check Updates
- Should see "You're Up to Date"
- Create update in license server
- Refresh check updates page
- Should see update available

---

## Testing Checklist

### ✅ License Server Tests
- [ ] Install works
- [ ] Login works
- [ ] Create license works
- [ ] License appears in list
- [ ] Create update works
- [ ] API verification works
- [ ] API updates works

### ✅ Client Platform Tests
- [ ] Installation wizard loads
- [ ] License verification works
- [ ] Site config saves
- [ ] Database setup works
- [ ] Installation completes
- [ ] Admin login works
- [ ] Check updates works
- [ ] Update installation works

---

## Common Issues & Quick Fixes

**Issue:** License server not accessible
- **Fix:** Update `install.php` line 44-45 to use `http://localhost/license-server`

**Issue:** License verification fails
- **Fix:** Make sure license status is "active" in license server

**Issue:** Update not showing
- **Fix:** Make sure download URL is set in update
- **Fix:** Clear browser cache

**Issue:** Installation hangs
- **Fix:** Check database connection
- **Fix:** Verify schema.sql exists

---

## Test Data

**License Server Admin:**
- Username: `admin`
- Password: `admin123`

**Test License:**
- Domain: `localhost`
- Status: `active`
- Type: `lifetime`

**Test Client Admin:**
- Username: `testadmin`
- Password: `Test123!@#`

**Test Database:**
- Host: `localhost`
- User: `root`
- Password: (empty)
- Database: `music_test`


