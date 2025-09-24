# 🏥 TeleHealth Platform - Complete Setup Guide

## 📋 Table of Contents
1. [System Requirements](#system-requirements)
2. [Installation Methods](#installation-methods)
3. [Database Setup](#database-setup)
4. [Application Configuration](#application-configuration)
5. [Web Server Configuration](#web-server-configuration)
6. [Production Deployment](#production-deployment)
7. [Testing the Setup](#testing-the-setup)
8. [Troubleshooting](#troubleshooting)

---

## 🖥️ System Requirements

### Minimum Requirements
- **Operating System**: Windows 10/11, Linux (Ubuntu 20.04+), macOS 10.15+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: 8.0 or higher
- **Database**: MySQL 8.0+ or MariaDB 10.4+
- **RAM**: 4GB minimum, 8GB recommended
- **Storage**: 2GB free space minimum

### Required PHP Extensions
```bash
php-pdo
php-pdo-mysql
php-gd
php-mbstring
php-json
php-session
php-curl
php-zip
php-fileinfo
```

---

## 🚀 Installation Methods

### Method 1: XAMPP Installation (Recommended for Development)

#### Step 1: Download and Install XAMPP
1. Download XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Install XAMPP with PHP 8.0+ and MySQL
3. Start Apache and MySQL services from XAMPP Control Panel

#### Step 2: Deploy Project
1. Copy the `telehealth` folder to `C:\xampp\htdocs\` (Windows) or `/opt/lampp/htdocs/` (Linux)
2. Ensure the folder structure is: `xampp/htdocs/telehealth/`

### Method 2: Manual LAMP/WAMP Stack Installation

#### For Ubuntu/Linux:
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Apache
sudo apt install apache2 -y

# Install MySQL
sudo apt install mysql-server -y

# Install PHP and extensions
sudo apt install php8.0 php8.0-cli php8.0-fpm php8.0-json php8.0-pdo php8.0-mysql php8.0-zip php8.0-gd php8.0-mbstring php8.0-curl php8.0-xml php8.0-bcmath -y

# Enable Apache modules
sudo a2enmod rewrite
sudo a2enmod php8.0
sudo systemctl restart apache2
```

#### For Windows:
1. Download Apache, PHP, and MySQL separately
2. Configure manually or use pre-built stacks like WampServer

---

## 🗄️ Database Setup

### Step 1: Create Database
1. Open phpMyAdmin (`http://localhost/phpmyadmin`) or use MySQL command line
2. Create a new database named `telehealth_db`

**Using phpMyAdmin:**
- Click "New" → Database name: `telehealth_db` → Create

**Using Command Line:**
```sql
mysql -u root -p
CREATE DATABASE telehealth_db;
EXIT;
```

### Step 2: Import Database Structure
Choose one of the following database files from the `database/` folder:

#### Option 1: Fresh Installation (Recommended)
```bash
# Navigate to project directory
cd /path/to/telehealth

# Import fresh database
mysql -u root -p telehealth_db < database/fresh_telehealth_db.sql
```

#### Option 2: With Sample Data
```bash
mysql -u root -p telehealth_db < database/telehealth_db.sql
```

#### Option 3: Alternative Schema
```bash
mysql -u root -p telehealth_db < database/fixed_telehealth_db.sql
```

### Step 3: Create Database User (Production)
```sql
CREATE USER 'telehealth_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON telehealth_db.* TO 'telehealth_user'@'localhost';
FLUSH PRIVILEGES;
```

---

## ⚙️ Application Configuration

### Step 1: Configure Database Connection
Edit `config.php` file:

```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'telehealth_db');
define('DB_USER', 'root'); // Change to 'telehealth_user' for production
define('DB_PASS', ''); // Add your password for production

// Site Configuration
define('SITE_URL', 'http://localhost/telehealth'); // Change for production
define('SITE_NAME', 'TeleHealth Platform');

// Security Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('CSRF_TOKEN_LENGTH', 32);

// Upload Configuration
define('MAX_FILE_SIZE', 5242880); // 5MB
define('UPLOAD_PATH', 'assets/images/profiles/');

// Email Configuration (optional)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');

// Environment
define('ENVIRONMENT', 'development'); // Change to 'production' for live site
?>
```

### Step 2: Set File Permissions
```bash
# For Linux/macOS
chmod 644 *.php
chmod 755 */
chmod 755 assets/images/profiles/
chmod 666 assets/images/profiles/

# Make upload directory writable
sudo chown -R www-data:www-data assets/images/profiles/
sudo chmod -R 755 assets/images/profiles/
```

### Step 3: Create Required Directories
```bash
mkdir -p assets/images/profiles
mkdir -p logs
mkdir -p tmp
```

---

## 🌐 Web Server Configuration

### Apache Configuration
Create/edit `.htaccess` in the root directory:

```apache
RewriteEngine On

# Security Headers
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# HTTPS Redirect (for production)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Pretty URLs
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([^/]+)/?$ $1.php [L,QSA]

# Deny access to sensitive files
<Files ~ "^(config|\.htaccess|\.env)">
    Order allow,deny
    Deny from all
</Files>

# Cache static assets
<IfModule mod_expires.c>
    ExpiresActive on
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
</IfModule>
```

### Nginx Configuration (Alternative)
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/telehealth;
    index index.php index.html;

    location / {
        try_files $uri $uri/ $uri.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location ~ /\. {
        deny all;
    }
}
```

---

## 🚀 Production Deployment

### Step 1: Environment Configuration
1. **Update config.php for production:**
```php
define('ENVIRONMENT', 'production');
define('SITE_URL', 'https://your-domain.com');
define('DB_PASS', 'your_secure_database_password');
```

2. **Enable error logging:**
```php
// In config.php
if (ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', 'logs/php_errors.log');
}
```

### Step 2: SSL Certificate Setup
```bash
# Using Let's Encrypt (free)
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d your-domain.com
```

### Step 3: Security Hardening
```bash
# Remove server signatures
echo "ServerTokens Prod" >> /etc/apache2/apache2.conf
echo "ServerSignature Off" >> /etc/apache2/apache2.conf

# Restart services
sudo systemctl restart apache2
sudo systemctl restart mysql
```

### Step 4: Backup Setup
```bash
# Create backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u root -p telehealth_db > backups/db_backup_$DATE.sql
tar -czf backups/files_backup_$DATE.tar.gz /var/www/telehealth

# Schedule daily backups (crontab -e)
0 2 * * * /path/to/backup_script.sh
```

---

## 🧪 Testing the Setup

### Step 1: Access the Application
1. Open your web browser
2. Navigate to: `http://localhost/telehealth` (development) or `https://your-domain.com` (production)

### Step 2: Verify Core Functionality

#### Database Connection Test
- The homepage should load without database errors
- Login page should be accessible

#### User Registration Test
1. Go to `http://localhost/telehealth/register.php`
2. Register as a patient (should work immediately)
3. Register as a doctor (should show "pending approval" message)

#### Admin Access Test
1. Create admin user manually in database:
```sql
INSERT INTO users (username, email, password, role, first_name, last_name, status)
VALUES ('admin', 'admin@example.com', '$2y$10$YourHashedPasswordHere', 'admin', 'System', 'Administrator', 'active');
```
2. Login as admin and access dashboard

#### Key URLs to Test
- **Homepage**: `/telehealth/`
- **Login**: `/telehealth/login.php`
- **Register**: `/telehealth/register.php`
- **Admin Dashboard**: `/telehealth/admin/`
- **Patient Dashboard**: `/telehealth/patient/`
- **Doctor Dashboard**: `/telehealth/doctor/`

---

## 🔧 Troubleshooting

### Common Issues and Solutions

#### Issue: Database Connection Failed
**Solution:**
1. Check MySQL service is running
2. Verify database credentials in `config.php`
3. Ensure database `telehealth_db` exists
4. Check PHP PDO extension is installed

```bash
# Check MySQL status
sudo systemctl status mysql

# Test PHP PDO
php -m | grep pdo
```

#### Issue: Permission Denied Errors
**Solution:**
```bash
# Fix file permissions
sudo chown -R www-data:www-data /var/www/telehealth
sudo chmod -R 755 /var/www/telehealth
sudo chmod -R 766 /var/www/telehealth/assets/images/profiles
```

#### Issue: Video Calls Not Working
**Solutions:**
1. Ensure HTTPS is enabled (required for WebRTC)
2. Check browser permissions for camera/microphone
3. Verify Jitsi Meet external API is accessible
4. Test with different browsers

#### Issue: File Upload Errors
**Solution:**
```bash
# Check PHP upload settings
php -i | grep upload

# Update php.ini if needed
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20
```

#### Issue: Session Errors
**Solution:**
```bash
# Ensure session directory is writable
sudo chmod 777 /tmp
# Or specify custom session path in php.ini
session.save_path = "/var/www/telehealth/tmp/sessions"
```

### Log File Locations
- **Apache Error Log**: `/var/log/apache2/error.log`
- **MySQL Error Log**: `/var/log/mysql/error.log`
- **PHP Error Log**: `/var/log/php_errors.log` or as configured
- **Application Logs**: `telehealth/logs/` (if configured)

### Performance Optimization
```bash
# Enable PHP OpCache
sudo apt install php8.0-opcache

# Configure in php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
```

---

## 📞 Support and Maintenance

### Regular Maintenance Tasks
1. **Database Backups**: Daily automated backups
2. **Log Rotation**: Clear old log files monthly
3. **Security Updates**: Keep all software updated
4. **Performance Monitoring**: Monitor server resources
5. **SSL Certificate Renewal**: Auto-renew Let's Encrypt certificates

### Monitoring Commands
```bash
# Check disk space
df -h

# Check memory usage
free -m

# Check active sessions
netstat -an | grep :80 | wc -l

# Monitor MySQL processes
mysql -u root -p -e "SHOW PROCESSLIST;"
```

---

## ✅ Setup Completion Checklist

- [ ] System requirements verified
- [ ] Web server installed and running
- [ ] PHP with required extensions installed
- [ ] MySQL database server running
- [ ] Database `telehealth_db` created and imported
- [ ] Application files deployed
- [ ] `config.php` configured
- [ ] File permissions set correctly
- [ ] Web server configuration applied
- [ ] SSL certificate installed (production)
- [ ] Admin user created
- [ ] Core functionality tested
- [ ] Backup system configured
- [ ] Error logging enabled
- [ ] Security measures implemented

---

## 🎉 Congratulations!

Your TeleHealth platform is now ready for use! The system includes:

- ✅ Complete user management (Admin, Doctors, Patients)
- ✅ Doctor approval system
- ✅ Appointment booking and management
- ✅ Video consultation capability
- ✅ Digital prescription system
- ✅ Payment processing
- ✅ Comprehensive admin panel
- ✅ Security implementations
- ✅ Responsive design

For additional features, customizations, or support, refer to the `PRODUCTION_STRUCTURE.md` file for system architecture details.

**Happy Healing! 🏥💻**