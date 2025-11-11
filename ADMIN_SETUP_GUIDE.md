# Admin Panel Setup Guide

## 🚀 Quick Start

Follow these steps to set up the admin panel for your music platform:

### Step 1: Run Database Migration

Execute the admin schema to add necessary tables and fields:

```sql
-- Option 1: Using MySQL command line
mysql -u your_username -p your_database < database/admin-schema.sql

-- Option 2: Using phpMyAdmin
-- Import the file: database/admin-schema.sql
```

This will:
- Add `role` column to users table
- Add `status` column to songs table
- Add `is_banned` and `banned_reason` to users table
- Create `admin_logs` table for activity tracking
- Create `news` table for content management
- Create a default super admin account

### Step 2: Create Your Admin Account

**Option A: Use the default account (created automatically)**
- Email: `admin@musicplatform.com`
- Password: `password`
- ⚠️ **Change this password immediately after first login!**

**Option B: Create a new admin manually**

```sql
-- Update an existing user to admin
UPDATE users SET role = 'super_admin' WHERE email = 'your@email.com';

-- Or create a new admin user
INSERT INTO users (username, email, password, role, email_verified, is_active)
VALUES ('admin', 'admin@yourdomain.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 1, 1);
-- Note: Password hash above is for 'password' - change it!
```

To generate a new password hash:
```php
<?php
echo password_hash('your_secure_password', PASSWORD_DEFAULT);
?>
```

### Step 3: Set Directory Permissions

Ensure upload directories have correct permissions:

```bash
chmod 755 uploads/images
chmod 755 uploads/audio
chmod 755 uploads/covers
```

### Step 4: Access the Admin Panel

Navigate to: `http://yourdomain.com/admin/login.php`

Login with your admin credentials.

## 📋 What's Included

### Admin Features:

1. **Dashboard** (`/admin/index.php`)
   - Platform statistics
   - Recent activity
   - Quick overview

2. **User Management** (`/admin/users.php`)
   - View all users
   - Ban/unban users
   - Change roles
   - Delete users

3. **Song Management** (`/admin/songs.php`)
   - Approve/reject songs
   - Feature songs
   - Delete songs
   - Monitor plays/downloads

4. **Artist Management** (`/admin/artists.php`)
   - Verify artists
   - View statistics
   - Manage profiles

5. **News Management** (`/admin/news.php`)
   - Create articles
   - Edit content
   - Publish/draft
   - Feature articles

6. **Analytics** (`/admin/analytics.php`)
   - Platform metrics
   - Top artists/songs
   - User growth
   - Genre distribution

7. **Settings** (`/admin/settings.php`)
   - System configuration
   - Upload limits
   - Download limits
   - Site preferences

## 🔐 User Roles

| Role | Access Level | Permissions |
|------|--------------|-------------|
| **user** | Regular user | Basic platform access |
| **artist** | Content creator | Upload songs, artist dashboard |
| **admin** | Administrator | Full admin panel access |
| **super_admin** | Super Admin | All permissions + settings |

## 🎨 Admin Interface

The admin panel features:
- ✅ Responsive design (mobile-friendly)
- ✅ Modern, clean UI
- ✅ Dark sidebar navigation
- ✅ Real-time statistics
- ✅ Search and filter capabilities
- ✅ Pagination for large datasets
- ✅ Activity logging
- ✅ Secure authentication

## 📝 Common Tasks

### Make a User an Admin
```sql
UPDATE users SET role = 'admin' WHERE email = 'user@example.com';
```

### Reset Admin Password
```php
<?php
// Generate new hash
$new_password = password_hash('new_secure_password', PASSWORD_DEFAULT);

// Update in database
UPDATE users SET password = 'hash_here' WHERE email = 'admin@example.com';
?>
```

### View Admin Activity Logs
```sql
SELECT * FROM admin_logs ORDER BY created_at DESC LIMIT 50;
```

## 🛡️ Security Best Practices

1. **Change Default Password**
   - Immediately change the default admin password
   - Use a strong, unique password

2. **Restrict Admin Access**
   - Only give admin role to trusted users
   - Regularly audit admin accounts

3. **Monitor Activity**
   - Check admin logs regularly
   - Look for suspicious activity

4. **Keep Backups**
   - Regular database backups
   - Store backups securely

5. **Update Regularly**
   - Keep PHP and MySQL updated
   - Apply security patches

## 🐛 Troubleshooting

### Cannot Login
```
Problem: "Access denied" or redirect loop
Solution:
1. Clear browser cookies
2. Check if user role is 'admin' or 'super_admin' in database
3. Verify session configuration in PHP
```

### Missing Tables
```
Problem: Database errors about missing tables
Solution:
Run the admin schema: database/admin-schema.sql
```

### Images Not Uploading
```
Problem: News images fail to upload
Solution:
1. Check directory exists: uploads/images/
2. Set permissions: chmod 755 uploads/images
3. Check PHP upload_max_filesize setting
```

### Sidebar Not Showing
```
Problem: Admin sidebar not visible on mobile
Solution:
1. Click the menu toggle button (☰)
2. Clear browser cache
3. Check CSS file loaded: admin/assets/css/admin.css
```

## 📞 Support

If you encounter issues:

1. Check the admin README: `admin/README.md`
2. Review PHP error logs
3. Check browser console for JavaScript errors
4. Verify database connection in `config/database.php`

## 🎯 Next Steps

After setup:

1. ✅ Change default admin password
2. ✅ Configure system settings
3. ✅ Add news categories
4. ✅ Review and approve pending songs
5. ✅ Create your first news article
6. ✅ Verify artists
7. ✅ Monitor analytics

## 📚 Additional Resources

- Admin Panel Documentation: `admin/README.md`
- Database Schema: `database/schema.sql`
- Admin Schema: `database/admin-schema.sql`

---

**Congratulations! Your admin panel is ready to use!** 🎉

Access it at: `http://yourdomain.com/admin/login.php`

