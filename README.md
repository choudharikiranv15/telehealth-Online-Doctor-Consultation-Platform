# TeleHealth Platform

A comprehensive telemedicine platform built with PHP, MySQL, and modern web technologies that enables patients to connect with healthcare providers through secure video consultations.

## 🌟 Features

### For Patients

- **User Registration & Authentication**: Secure patient accounts with role-based access
- **Appointment Booking**: Schedule consultations with available doctors
- **Video Consultations**: High-quality video calls using Jitsi Meet
- **Prescription Management**: View and download medical prescriptions
- **Profile Management**: Update personal and medical information
- **Appointment History**: Track past and upcoming consultations

### For Doctors

- **Professional Profiles**: Detailed doctor profiles with specializations
- **Appointment Management**: View and manage patient appointments
- **Video Consultations**: Conduct secure video consultations
- **Patient Records**: Access patient medical history and information
- **Prescription Creation**: Generate and manage patient prescriptions
- **Schedule Management**: Set availability and working hours

### For Administrators

- **User Management**: Manage doctors and patient accounts
- **System Monitoring**: View platform statistics and activity
- **Content Management**: Control platform content and settings
- **Reporting**: Generate reports on platform usage

## 🚀 Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Framework**: Bootstrap 5.1.3
- **Video Calling**: Jitsi Meet API
- **Icons**: Font Awesome 6.0
- **Security**: PDO with prepared statements, password hashing

## 📁 Project Structure

```
telehealth/
├── admin/                 # Admin panel files
│   ├── index.php         # Admin dashboard
│   ├── manage_doctors.php # Doctor management
│   └── manage_patients.php # Patient management
├── assets/               # Static assets
│   ├── css/             # Stylesheets
│   ├── js/              # JavaScript files
│   └── images/          # Images and media
├── controllers/          # Business logic controllers
│   ├── auth_controller.php    # Authentication
│   ├── appointment_controller.php # Appointments
│   └── profile_controller.php # User profiles
├── database/            # Database files
│   └── telehealth_db.sql # Database schema
├── doctor/              # Doctor panel files
│   ├── index.php        # Doctor dashboard
│   ├── my_appointments.php # Appointment management
│   └── profile.php      # Profile management
├── includes/            # Common includes
│   ├── header.php       # Page header
│   ├── footer.php       # Page footer
│   └── db_connect.php   # Database connection
├── lib/                 # Third-party libraries
├── patient/             # Patient panel files
│   ├── index.php        # Patient dashboard
│   ├── book_appointment.php # Appointment booking
│   └── my_prescriptions.php # Prescription management
├── .htaccess           # Apache configuration
├── config.php          # Application configuration
├── index.php           # Landing page
├── login.php           # Login page
├── register.php        # Registration page
└── README.md           # This file
```

## 🛠️ Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (optional, for dependency management)

### Step 1: Clone the Repository

```bash
git clone https://github.com/yourusername/telehealth.git
cd telehealth
```

### Step 2: Database Setup

1. Create a new MySQL database:

```sql
CREATE DATABASE telehealth_db;
```

2. Import the database schema:

```bash
mysql -u yourusername -p telehealth_db < database/telehealth_db.sql
```

### Step 3: Configuration

1. Copy and modify the configuration file:

```bash
cp config.php.example config.php
```

2. Update database credentials in `config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'telehealth_db');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
```

3. Update application settings:

```php
define('SITE_URL', 'http://yourdomain.com/telehealth');
define('ADMIN_EMAIL', 'admin@yourdomain.com');
```

### Step 4: Web Server Configuration

1. **Apache**: Ensure mod_rewrite is enabled
2. **Nginx**: Configure URL rewriting for clean URLs
3. Set proper file permissions:

```bash
chmod 755 -R telehealth/
chmod 777 -R assets/images/
```

### Step 5: Test Installation

1. Open your browser and navigate to the installation URL
2. You should see the TeleHealth landing page
3. Test user registration and login

## 🔐 Default Accounts

After installation, the following default accounts are available:

### Admin Account

- **Username**: admin
- **Password**: password
- **Email**: admin@telehealth.com

### Sample Doctor

- **Username**: dr.smith
- **Password**: password
- **Email**: dr.smith@telehealth.com

### Sample Patient

- **Username**: patient1
- **Password**: password
- **Email**: patient1@telehealth.com

**⚠️ Important**: Change these default passwords immediately after installation!

## 🎥 Video Calling Setup

The platform uses Jitsi Meet for video consultations. No additional setup is required as it uses the public Jitsi Meet service.

### Features

- High-quality video and audio
- Screen sharing
- Recording capabilities
- Chat functionality
- Participant management

## 🔒 Security Features

- **Password Hashing**: Bcrypt password encryption
- **SQL Injection Protection**: PDO prepared statements
- **XSS Protection**: Input sanitization and output escaping
- **CSRF Protection**: Form token validation
- **Session Security**: Secure session management
- **File Upload Security**: File type and size validation

## 📱 Responsive Design

The platform is fully responsive and works on:

- Desktop computers
- Tablets
- Mobile phones
- All modern web browsers

## 🚀 Performance Optimization

- **CSS/JS Minification**: Optimized asset delivery
- **Image Optimization**: Compressed images for faster loading
- **Database Indexing**: Optimized database queries
- **Caching**: Browser and server-side caching

## 🧪 Testing

### Manual Testing

1. **User Registration**: Test patient and doctor registration
2. **Login System**: Verify authentication works correctly
3. **Appointment Booking**: Test the complete appointment flow
4. **Video Calls**: Test video consultation functionality
5. **Admin Panel**: Verify admin controls work properly

### Automated Testing

```bash
# Run PHP unit tests (if implemented)
php vendor/bin/phpunit

# Run code quality checks
php vendor/bin/phpcs
```

## 🐛 Troubleshooting

### Common Issues

#### Database Connection Error

- Verify database credentials in `config.php`
- Ensure MySQL service is running
- Check database permissions

#### Video Call Not Working

- Ensure JavaScript is enabled
- Check browser console for errors
- Verify internet connection
- Test with different browsers

#### File Upload Issues

- Check file permissions on `assets/images/` directory
- Verify `upload_max_filesize` in PHP configuration
- Ensure proper file type validation

#### 404 Errors

- Enable Apache mod_rewrite
- Check `.htaccess` file exists
- Verify web server configuration

### Debug Mode

Enable debug mode in `config.php`:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 🔄 Updates & Maintenance

### Regular Maintenance

1. **Database Backups**: Regular database backups
2. **Security Updates**: Keep PHP and dependencies updated
3. **Log Monitoring**: Monitor error logs for issues
4. **Performance Monitoring**: Track platform performance

### Updating the Platform

1. Backup your database and files
2. Download the latest version
3. Replace files (preserve custom configurations)
4. Run database migrations if needed
5. Test functionality

## 📊 Monitoring & Analytics

### Built-in Analytics

- User registration statistics
- Appointment booking metrics
- Video call usage data
- System performance indicators

### External Analytics

Integrate with Google Analytics or similar services for detailed insights.

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

### Coding Standards

- Follow PSR-12 coding standards
- Add proper documentation
- Include error handling
- Test your changes thoroughly

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

### Documentation

- Check this README file
- Review inline code comments
- Check the issues section on GitHub

### Getting Help

- **GitHub Issues**: Report bugs and request features
- **Email Support**: Contact admin@telehealth.com
- **Community Forum**: Join our community discussions

### Professional Support

For enterprise support and custom development, contact our team.

## 🙏 Acknowledgments

- **Jitsi Meet**: Video calling infrastructure
- **Bootstrap**: Frontend framework
- **Font Awesome**: Icon library
- **PHP Community**: Open source contributions

## 📈 Roadmap

### Upcoming Features

- [ ] Mobile app (iOS/Android)
- [ ] Payment integration
- [ ] Electronic health records
- [ ] AI-powered symptom analysis
- [ ] Multi-language support
- [ ] Advanced reporting
- [ ] Integration with medical devices

### Version History

- **v1.0.0**: Initial release with core functionality
- **v1.1.0**: Enhanced security and performance
- **v1.2.0**: Additional features and improvements

---

**Built with ❤️ for better healthcare accessibility**

For more information, visit our website or contact our team.
