# 📋 Deployment Checklist

Use this checklist when deploying your TeleHealth platform to production.

---

## 🔧 Pre-Deployment Preparation

### 1. Configuration Files

- [ ] **Update `config.php`** with production database credentials
  ```php
  define('DB_HOST', 'your-production-host');
  define('DB_NAME', 'your-production-database');
  define('DB_USER', 'your-production-user');
  define('DB_PASS', 'your-production-password');
  define('SITE_URL', 'https://yourdomain.com');
  ```

- [ ] **Set environment to production** (if using environment variables)
  ```bash
  APP_ENV=production
  ```

- [ ] **Enable HTTPS redirect in `.htaccess`**
  - Uncomment the HTTPS redirect lines in `.htaccess`
  ```apache
  RewriteCond %{HTTPS} off
  RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
  ```

### 2. Database Setup

- [ ] **Create production database** on hosting provider
- [ ] **Import database schema**: Upload `database/complete_telehealth_db.sql`
- [ ] **Verify database structure**: Run `verify_db.php` to confirm all tables exist
- [ ] **Test database connection**: Check homepage loads without errors

### 3. Security Hardening

- [ ] **Change default admin password**
  ```sql
  UPDATE users
  SET password = MD5('your_new_secure_password')
  WHERE email = 'admin@telehealth.com';
  ```

- [ ] **Remove or secure test accounts**
  ```sql
  -- Review all users
  SELECT id, email, role FROM users;

  -- Delete test accounts if needed
  DELETE FROM users WHERE email LIKE '%test%';
  ```

- [ ] **Disable error display** (already done in config.php)
  - Verify `display_errors = 0` in production mode

- [ ] **Review file permissions**
  - Folders: `755` (drwxr-xr-x)
  - Files: `644` (-rw-r--r--)
  - `config.php`: `600` (recommended) or `644`

- [ ] **Verify `.htaccess` protections are active**
  - Test: Try accessing `https://yourdomain.com/config.php` (should be forbidden)
  - Test: Try accessing `https://yourdomain.com/database/` (should be forbidden)

### 4. File Cleanup

- [ ] **Remove development files**
  - [ ] Delete `test_*.php` files (if any)
  - [ ] Delete any debug scripts
  - [ ] Remove `.env.example` (keep only if needed for reference)

- [ ] **Verify essential files remain**
  - [ ] `database/complete_telehealth_db.sql` ✅
  - [ ] `verify_db.php` ✅
  - [ ] All application PHP files ✅

### 5. Uploads & Permissions

- [ ] **Create uploads directory** (if not exists)
  ```bash
  mkdir -p assets/images/profiles
  mkdir -p assets/images/prescriptions
  chmod 755 assets/images
  ```

- [ ] **Verify write permissions** for uploads folder
  - Test by uploading a profile picture

---

## 🚀 Deployment Steps

### For InfinityFree / 000webhost / Traditional Hosting:

- [ ] **Upload files via FTP or File Manager**
  - Upload all files to `htdocs` or `public_html` folder
  - Maintain folder structure

- [ ] **Create database** in hosting control panel
  - Note: Database name, username, password

- [ ] **Import SQL file** via phpMyAdmin
  - Select database → Import → Choose `complete_telehealth_db.sql`

- [ ] **Update `config.php`** with hosting database credentials

- [ ] **Set up SSL certificate** (if available)
  - Use Cloudflare free SSL, or hosting provider's SSL

### For Railway / Render / Cloud Platforms:

- [ ] **Connect GitHub repository**

- [ ] **Add environment variables** in dashboard:
  ```
  DB_HOST=<from platform>
  DB_NAME=<from platform>
  DB_USER=<from platform>
  DB_PASS=<from platform>
  SITE_URL=https://yourapp.railway.app
  APP_ENV=production
  ```

- [ ] **Deploy application**

- [ ] **Add MySQL database** service

- [ ] **Import SQL** via platform's database tools

---

## ✅ Post-Deployment Verification

### 1. Basic Functionality

- [ ] **Homepage loads** without errors
- [ ] **Login works** (test with admin account)
- [ ] **Registration works** (create test patient account)
- [ ] **Dashboard displays correctly** for all roles (admin, doctor, patient)

### 2. Core Features

- [ ] **Doctor listing** shows on homepage
- [ ] **Appointment booking** works
- [ ] **Appointment management** works (approve/reject/complete)
- [ ] **Prescription creation** works
- [ ] **Profile updates** work (patient & doctor)
- [ ] **Image uploads** work (profile pictures)

### 3. Video Calls (Requires HTTPS)

- [ ] **HTTPS is enabled** (check URL shows padlock 🔒)
- [ ] **WebRTC video calls work** (test in Chrome/Firefox)
  - Book appointment
  - Join video call as doctor
  - Join video call as patient
  - Verify video/audio works

- [ ] **Missed call tracking** works
  - Test: One person joins, other doesn't for 15+ minutes
  - Verify status changes to "missed"

### 4. Special Features

- [ ] **Password reset** works (if email configured)
- [ ] **Rejection reasons** display correctly
- [ ] **Doctor ratings/reviews** work
- [ ] **Payment tracking** works (if integrated)
- [ ] **Notifications** display

### 5. Security Checks

- [ ] **Cannot access** `config.php` directly in browser
- [ ] **Cannot access** SQL files in `database/` folder
- [ ] **Cannot access** `includes/` folder directly
- [ ] **SQL injection protection** (basic test: try `' OR '1'='1` in login)
- [ ] **Session management** works (login, logout, timeout)
- [ ] **Role-based access** enforced (patient can't access admin panel)

### 6. Performance & Monitoring

- [ ] **Page load times** are acceptable
- [ ] **Images load** properly
- [ ] **CSS/JS files** load correctly
- [ ] **Error logs** configured and working
  - Check `error.log` file exists
  - Verify errors are logged (create intentional error to test)

---

## 📧 Optional: Email Configuration

If you want password reset and notifications to work:

- [ ] **Configure SMTP settings** in your code
- [ ] **Use free SMTP service**:
  - Gmail SMTP (requires App Password)
  - SendGrid (100 emails/day free)
  - Mailgun (100 emails/day free)

- [ ] **Test password reset email** sends successfully

---

## 🔄 Backup & Maintenance

### Set Up Regular Backups

- [ ] **Database backups** (weekly)
  ```bash
  mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
  ```

- [ ] **File backups** (monthly)
  - Backup entire application folder

- [ ] **Test backup restoration** at least once

### Monitoring

- [ ] **Set up uptime monitoring** (optional)
  - Use: UptimeRobot (free), Pingdom, StatusCake

- [ ] **Monitor error logs** regularly

- [ ] **Check database size** periodically

---

## 🎯 Production Checklist Summary

Quick checklist of critical items:

```
✅ Database imported and connected
✅ Admin password changed
✅ Test users removed
✅ HTTPS enabled
✅ Error display disabled
✅ Sensitive files protected (.htaccess working)
✅ Video calls tested (HTTPS required)
✅ All core features tested
✅ Backup system in place
✅ Error logging configured
```

---

## 🆘 Troubleshooting Common Issues

### "Database connection failed"
- Double-check DB credentials in `config.php`
- Verify database host (often not 'localhost' on shared hosting)
- Check if database user has proper permissions

### "500 Internal Server Error"
- Check `error.log` file for details
- Verify PHP version compatibility (PHP 7.4+ recommended)
- Check file permissions (755 for folders, 644 for files)
- Verify `.htaccess` syntax

### "Video calls not working"
- HTTPS is **required** for WebRTC
- Check browser console for errors
- Test on different browsers (Chrome/Firefox work best)
- Verify HTTPS certificate is valid

### "Images not uploading"
- Check `assets/images/` folder permissions (755 or 777)
- Verify `MAX_FILE_SIZE` in `config.php`
- Check hosting upload limits

### "Session issues / Can't stay logged in"
- Check PHP session configuration on hosting
- Verify `session.save_path` has write permissions
- Check hosting doesn't restrict sessions

---

## 📝 Default Credentials (from sample data)

**Admin:**
- Email: `admin@telehealth.com`
- Password: Check your SQL file for default

**IMPORTANT:** Change these immediately after deployment!

---

## ✅ Deployment Complete!

Once all items are checked:
1. Announce to your users
2. Monitor for 24-48 hours
3. Address any issues promptly
4. Set up regular maintenance schedule

**Congratulations on your deployment! 🎉**
