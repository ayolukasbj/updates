# Music Streaming Platform

A comprehensive music streaming and downloading website built with PHP and MySQL. Features include user authentication, music upload, streaming, playlists, social features, and more.

## 🎵 Features

### Core Features
- **User Authentication**: Registration, login, password reset, email verification
- **Music Upload**: Support for MP3, WAV, FLAC, AAC formats
- **Audio Streaming**: HTML5 audio player with custom controls
- **Download System**: Secure downloads with quality options
- **Playlist Management**: Create, share, and manage playlists
- **Search & Discovery**: Advanced search with filters and recommendations

### Social Features
- **User Profiles**: Customizable user profiles with avatars
- **Favorites**: Save and manage favorite songs
- **Follow System**: Follow artists and other users
- **Reviews & Ratings**: Rate and review songs
- **Sharing**: Share songs and playlists

### Artist Features
- **Artist Dashboard**: Comprehensive analytics and management
- **Music Upload**: Bulk upload with metadata extraction
- **Album Management**: Create and manage albums
- **Revenue Tracking**: Track plays, downloads, and earnings

### Admin Features
- **Content Management**: Moderate content and manage users
- **Analytics Dashboard**: Detailed statistics and reports
- **Settings Management**: Configure site settings
- **User Management**: Manage user accounts and permissions

## 🚀 Installation

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- File upload permissions
- GD extension for image processing

### Quick Install

1. **Download/Clone the project**
   ```bash
   git clone https://github.com/yourusername/music-streaming-platform.git
   cd music-streaming-platform
   ```

2. **Set up web server**
   - Point your web server document root to the project directory
   - Ensure mod_rewrite is enabled (for Apache)

3. **Run the installer**
   - Navigate to `http://yourdomain.com/install.php`
   - Follow the installation wizard
   - Configure database settings
   - Create admin account

4. **Configure file permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/music/
   chmod 755 uploads/images/
   ```

### Manual Installation

1. **Create database**
   ```sql
   CREATE DATABASE music_streaming;
   ```

2. **Import schema**
   ```bash
   mysql -u username -p music_streaming < database/schema.sql
   ```

3. **Configure settings**
   - Edit `config/config.php`
   - Update database credentials
   - Configure file paths and settings

4. **Set up directories**
   ```bash
   mkdir -p uploads/music uploads/images
   chmod 755 uploads/music uploads/images
   ```

## 📁 Project Structure

```
music-streaming-platform/
├── api/                    # API endpoints
│   ├── song.php
│   ├── upload.php
│   └── play-history.php
├── assets/                 # Static assets
│   ├── css/
│   ├── js/
│   └── images/
├── classes/               # PHP classes
│   ├── User.php
│   ├── Song.php
│   ├── Artist.php
│   ├── Album.php
│   └── Playlist.php
├── config/               # Configuration files
│   ├── config.php
│   └── database.php
├── controllers/          # Controllers
│   └── AuthController.php
├── database/            # Database files
│   └── schema.sql
├── uploads/              # Upload directories
│   ├── music/
│   └── images/
├── views/               # View templates
│   └── auth/
├── index.php            # Homepage
├── dashboard.php        # User dashboard
├── upload.php          # Music upload
├── login.php           # Login page
├── register.php        # Registration page
└── install.php         # Installation script
```

## 🎛️ Configuration

### Database Configuration
Edit `config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'music_streaming');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### File Upload Settings
```php
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_AUDIO_FORMATS', ['mp3', 'wav', 'flac', 'aac', 'm4a']);
```

### Email Configuration
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

## 🎨 Customization

### Themes
- Modify CSS files in `assets/css/`
- Update color variables in `main.css`
- Customize component styles

### Features
- Enable/disable features in `config/config.php`
- Modify user permissions and subscription types
- Add custom audio processing

### API
- Extend API endpoints in `api/` directory
- Add new functionality to existing classes
- Implement additional integrations

## 📱 Mobile Support

The platform is fully responsive and includes:
- Mobile-first design
- Touch-friendly controls
- Progressive Web App features
- Offline playlist support

## 🔒 Security Features

- SQL injection prevention
- XSS protection
- CSRF tokens
- File upload validation
- User input sanitization
- Secure password hashing
- Session management

## 🎵 Audio Features

- Multiple format support (MP3, WAV, FLAC, AAC)
- Quality options (128kbps to lossless)
- Metadata extraction
- Audio visualization
- Crossfade support
- Equalizer (planned)

## 📊 Analytics

- Play count tracking
- Download statistics
- User engagement metrics
- Revenue analytics
- Geographic data
- Device statistics

## 🛠️ Development

### Adding New Features

1. **Create database tables** (if needed)
2. **Add PHP classes** in `classes/`
3. **Create API endpoints** in `api/`
4. **Add frontend views**
5. **Update JavaScript** in `assets/js/`

### Code Standards

- Follow PSR-4 autoloading
- Use prepared statements for database queries
- Validate all user inputs
- Implement proper error handling
- Document all functions

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

For support and questions:
- Create an issue on GitHub
- Check the documentation
- Contact the development team

## 🔄 Updates

### Version 1.0.0
- Initial release
- Core streaming functionality
- User management
- Music upload
- Playlist system
- Basic admin panel

### Planned Features
- Mobile app
- Advanced analytics
- Payment integration
- Social media integration
- AI recommendations
- Live streaming

## 🙏 Acknowledgments

- Bootstrap for UI framework
- Font Awesome for icons
- AOS for animations
- All contributors and testers

---

**Made with ❤️ for music lovers**
