# Admin Panel Documentation

## Overview
Comprehensive admin panel for full platform management of the Music Streaming Platform.

## Features

### 🔐 Authentication & Security
- Secure admin login system
- Role-based access control (Admin, Super Admin)
- Activity logging for all admin actions
- Protected routes with authentication middleware

### 📊 Dashboard
- Real-time statistics overview
- Total users, songs, artists, plays, and downloads
- Recent users and top songs display
- Admin activity logs
- Monthly user growth tracking

### 👥 User Management
- View all registered users
- Search and filter by role, status
- Ban/unban users with reason
- Change user roles (Super Admin only)
- Delete users (Super Admin only)
- View user details (join date, last login)

### 🎵 Song Management
- View all uploaded songs
- Search songs by title or artist
- Filter by status (approved/pending/rejected)
- Toggle featured status
- Approve/reject songs
- Delete songs
- View song statistics (plays, downloads)

### 🎤 Artist Management
- View all artists
- Search and filter artists
- Verify/unverify artists
- View artist statistics
- Delete artists
- Monitor artist performance

### 📰 News Management
- Create new articles
- Edit existing articles
- Delete articles
- Rich content editor
- Featured image upload
- Category management
- Publish/draft toggle
- Featured articles
- View statistics

### 📈 Analytics & Reports
- Platform-wide statistics
- Top artists by plays
- Top songs performance
- User growth charts
- Genre distribution analysis
- Download and play metrics

### ⚙️ System Settings
- General site configuration
- Upload settings (max size, allowed formats)
- Download limits (free/premium users)
- Streaming quality settings
- Registration controls
- Maintenance mode

## Installation

### 1. Database Setup
Run the admin schema SQL to add necessary tables:

```bash
mysql -u your_username -p your_database < database/admin-schema.sql
```

This will create:
- Admin logs table
- News table
- Add role column to users
- Add status column to songs
- Add banned fields to users

### 2. Access the Admin Panel

Navigate to: `http://yourdomain.com/admin/login.php`

### 3. Default Credentials

A super admin account is created automatically:
- **Email**: admin@musicplatform.com
- **Password**: password (Note: Change this immediately!)

To create the password hash for a new admin:
```php
<?php
echo password_hash('your_password', PASSWORD_DEFAULT);
?>
```

## File Structure

```
admin/
├── index.php              # Dashboard
├── login.php             # Admin login
├── logout.php            # Logout handler
├── auth-check.php        # Authentication middleware
├── users.php             # User management
├── songs.php             # Song management
├── artists.php           # Artist management
├── news.php              # News listing
├── news-edit.php         # News create/edit
├── analytics.php         # Analytics & reports
├── settings.php          # System settings
├── assets/
│   └── css/
│       └── admin.css     # Admin panel styles
└── includes/
    ├── header.php        # Header & sidebar
    └── footer.php        # Footer & scripts
```

## User Roles

### User (Default)
- Regular platform user
- Cannot access admin panel

### Artist
- Can upload songs
- View own dashboard
- Cannot access admin panel

### Admin
- Full access to admin panel
- Cannot change other admin roles
- Cannot delete super admins
- Can manage users, songs, artists, news

### Super Admin
- All admin privileges
- Can change any user role
- Can delete any user except other super admins
- Can access system settings
- Cannot be deleted by other admins

## Security Features

### Authentication
- Password hashing with bcrypt
- Session-based authentication
- Automatic session timeout
- Protected admin routes

### Access Control
- Role verification on every page
- Super admin-only features
- Ban system for problematic users
- IP address logging

### Activity Logging
All admin actions are logged including:
- User bans/unbans
- User role changes
- Song approvals/rejections
- News article changes
- Settings updates
- Deletions

## API Endpoints

The admin panel interacts with these database tables:
- `users` - User accounts
- `songs` - Song library
- `artists` - Artist profiles
- `news` - News articles
- `admin_logs` - Activity tracking
- `settings` - System configuration

## Customization

### Adding New Settings
Edit `admin/settings.php` and add new form fields:

```php
<div class="form-group">
    <label>Your Setting</label>
    <input type="text" name="settings[your_key]" class="form-control" 
           value="<?php echo htmlspecialchars($settings['your_key']['setting_value'] ?? 'default'); ?>">
</div>
```

### Adding New Menu Items
Edit `admin/includes/header.php` in the sidebar nav:

```php
<a href="your-page.php" class="nav-item">
    <i class="fas fa-icon"></i>
    <span>Your Page</span>
</a>
```

## Responsive Design
- Fully responsive admin interface
- Mobile-friendly navigation
- Collapsible sidebar on mobile
- Touch-friendly controls

## Browser Support
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Troubleshooting

### Cannot Login
- Verify database connection
- Check if admin schema is installed
- Ensure user has admin or super_admin role
- Clear browser cache and cookies

### Permission Denied
- Check user role in database
- Verify auth-check.php is included
- Check session configuration

### Images Not Uploading
- Check uploads/images/ directory permissions (755)
- Verify PHP upload_max_filesize setting
- Check max_upload_size in settings

## Maintenance

### Regular Tasks
1. Monitor admin logs for suspicious activity
2. Review and approve pending songs
3. Moderate news content
4. Check analytics for platform health
5. Backup database regularly

### Backup
Regular backups recommended:
```bash
mysqldump -u user -p database_name > backup_$(date +%Y%m%d).sql
```

## Support

For issues or questions:
- Check error logs: `admin/error.log`
- Review admin activity logs
- Check PHP error logs
- Verify database connection

## Updates

To update the admin panel:
1. Backup current files
2. Replace admin files
3. Run any new SQL migrations
4. Clear cache
5. Test in staging environment first

## License
Part of the Music Streaming Platform

## Version
1.0.0 - Initial Release

