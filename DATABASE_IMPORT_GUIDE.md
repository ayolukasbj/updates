# Database Import Guide

## 📋 Available Schema Files

You have 4 SQL schema files in the `database/` folder:

1. **`database/schema.sql`** - Main schema (users, artists, songs, albums, genres, playlists)
2. **`database/news-schema.sql`** - News tables (news, news_comments, news_views) + sample data
3. **`database/admin-schema.sql`** - Admin tables (admin_logs, role column, news table) + sample data
4. **`database/add-profile-columns.sql`** - Additional user profile columns

---

## ⚠️ Important Notes

- **News table conflict**: Both `news-schema.sql` and `admin-schema.sql` create a `news` table, but with different structures
- **Use `news-schema.sql`** for the news system (it has better structure with `is_published` field)
- The `admin-schema.sql` also creates a news table, but it's simpler

---

## 🚀 Recommended Import Order

### Option 1: Complete Fresh Import (Recommended)

**Step 1:** Import main schema
```
database/schema.sql
```
This creates: users, artists, songs, albums, genres, playlists

**Step 2:** Import news schema
```
database/news-schema.sql
```
This creates: news, news_comments, news_views + sample news data

**Step 3:** Import admin enhancements
```
database/admin-schema.sql
```
This adds: role column, admin_logs, status column to songs, is_banned to users

**Step 4:** Import profile columns (optional)
```
database/add-profile-columns.sql
```
This adds: bio, facebook, twitter, etc. to users table

---

## 📝 How to Import

### Method 1: Using phpMyAdmin (Easiest)

1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Select your database (e.g., `gospelki_ziki` or `music_streaming2`)
3. Click **"Import"** tab
4. Click **"Choose File"** and select the SQL file
5. Click **"Go"** at the bottom
6. Repeat for each file in the order above

### Method 2: Using Command Line

```bash
# Navigate to your database folder
cd C:\xampp\htdocs\music\database

# Import each file (replace DB_NAME and DB_USER with your values)
mysql -u root -p DB_NAME < schema.sql
mysql -u root -p DB_NAME < news-schema.sql
mysql -u root -p DB_NAME < admin-schema.sql
mysql -u root -p DB_NAME < add-profile-columns.sql
```

### Method 3: Using MySQL Workbench

1. Open MySQL Workbench
2. Connect to your database
3. File → Open SQL Script
4. Select the SQL file
5. Click the Execute button (⚡)
6. Repeat for each file

---

## ✅ After Import - Verify Tables

Run this query in phpMyAdmin to check if all tables exist:

```sql
SHOW TABLES;
```

You should see:
- ✅ users
- ✅ artists
- ✅ songs
- ✅ albums
- ✅ genres
- ✅ playlists
- ✅ news
- ✅ news_comments
- ✅ news_views
- ✅ admin_logs

---

## 🔧 If You Get Errors

### Error: "Table already exists"
- **Solution**: The files use `CREATE TABLE IF NOT EXISTS`, so this shouldn't happen
- If it does, drop the table first: `DROP TABLE IF EXISTS table_name;`

### Error: "Unknown column in field list"
- **Solution**: Make sure you imported files in the correct order
- Re-import `admin-schema.sql` to add missing columns

### Error: "Foreign key constraint fails"
- **Solution**: Make sure `users` table exists before importing other tables
- Import `schema.sql` first

---

## 🎯 Quick Import Script

If you want, I can create a PHP script that imports all files automatically. Just let me know!

---

## 📊 Expected Results

After importing all files, you should have:

- **Users table** with role column
- **Songs table** with all required columns (uploaded_by, status, etc.)
- **News table** with is_published field
- **Artists table** ready for use
- **Sample news data** (6 articles from news-schema.sql)
- **Admin logs table** for tracking admin actions

---

## 🐛 Troubleshooting

If tables still don't exist after import:

1. Check database name in `config/database.php` matches the one you imported to
2. Check user permissions in `config/database.php`
3. Verify import was successful in phpMyAdmin
4. Check PHP error logs for any connection issues

