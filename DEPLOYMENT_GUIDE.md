# 🚀 Free Deployment Guide - TeleHealth Platform

This guide covers multiple **FREE** hosting options for deploying your TeleHealth platform.

---

## 🎯 Best Free Options for This Project

### Option 1: InfinityFree (Recommended for Beginners) ⭐

**What you get FREE:**
- Unlimited bandwidth
- 5GB storage
- MySQL database
- PHP support
- Free subdomain (yoursite.rf.gd)
- Control panel (like cPanel)

**Steps:**
1. Sign up at https://infinityfree.net
2. Create a new account (choose subdomain)
3. Upload files via File Manager or FTP
4. Create MySQL database in control panel
5. Import `database/complete_telehealth_db.sql`
6. Update `config.php` with new database credentials

**Pros:**
- ✅ Easy to use
- ✅ No ads
- ✅ Good for testing/demo
- ✅ PHP + MySQL included

**Cons:**
- ⚠️ Limited requests per hour
- ⚠️ WebRTC video calls may have issues (need HTTPS)

---

### Option 2: Railway.app (Best for Production) ⭐⭐⭐

**What you get FREE:**
- $5 free credits per month
- HTTPS by default
- MySQL database
- Better performance
- Custom domain support

**Steps:**

1. **Prepare your project:**
   ```bash
   # Create Procfile in project root
   echo "web: php -S 0.0.0.0:\$PORT -t ." > Procfile
   ```

2. **Sign up at https://railway.app** (use GitHub)

3. **Create new project:**
   - Click "New Project"
   - Select "Deploy from GitHub repo"
   - Choose your repository

4. **Add MySQL database:**
   - Click "+ New"
   - Select "Database" → "MySQL"
   - Copy connection details

5. **Configure environment variables:**
   ```
   DB_HOST=<from Railway MySQL>
   DB_NAME=railway
   DB_USER=<from Railway MySQL>
   DB_PASS=<from Railway MySQL>
   DB_PORT=<from Railway MySQL>
   ```

6. **Update config.php to use environment variables:**
   ```php
   define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
   define('DB_USER', getenv('DB_USER') ?: 'root');
   define('DB_PASS', getenv('DB_PASS') ?: '');
   define('DB_NAME', getenv('DB_NAME') ?: 'telehealth');
   ```

7. **Import database:**
   - Use Railway's phpMyAdmin or MySQL client
   - Import `database/complete_telehealth_db.sql`

**Pros:**
- ✅ HTTPS included (required for WebRTC)
- ✅ Good performance
- ✅ Git-based deployment
- ✅ Professional setup

**Cons:**
- ⚠️ $5/month credit limit (may run out if high traffic)

---

### Option 3: 000webhost (Alternative) ⭐

**What you get FREE:**
- 300MB storage
- 3GB bandwidth
- 1 MySQL database
- PHP support
- Free subdomain

**Steps:**
1. Sign up at https://www.000webhost.com
2. Create new website
3. Upload files via File Manager
4. Create database and import SQL
5. Update config.php

**Pros:**
- ✅ Simple setup
- ✅ Control panel included

**Cons:**
- ⚠️ Shows ads
- ⚠️ Limited bandwidth
- ⚠️ Site sleeps if inactive

---

### Option 4: Render.com (Modern Alternative) ⭐⭐

**What you get FREE:**
- Free web service
- Free PostgreSQL database (750 hours/month)
- HTTPS included
- Auto-deploy from Git

**Steps:**

1. **Convert to PostgreSQL** (or use MySQL paid tier)

2. **Create render.yaml:**
   ```yaml
   services:
     - type: web
       name: telehealth
       env: php
       buildCommand: composer install --no-dev
       startCommand: php -S 0.0.0.0:$PORT -t .
       envVars:
         - key: DB_HOST
           fromDatabase:
             name: telehealth-db
             property: host
         - key: DB_NAME
           fromDatabase:
             name: telehealth-db
             property: database
         - key: DB_USER
           fromDatabase:
             name: telehealth-db
             property: user
         - key: DB_PASS
           fromDatabase:
             name: telehealth-db
             property: password

   databases:
     - name: telehealth-db
       databaseName: telehealth
       user: telehealth_user
   ```

3. **Push to GitHub and connect to Render**

**Pros:**
- ✅ Modern platform
- ✅ Git-based deployment
- ✅ HTTPS included

**Cons:**
- ⚠️ PostgreSQL instead of MySQL (requires conversion)
- ⚠️ More complex setup

---

## 🔥 Quick Start: InfinityFree (5 Minutes)

**Step-by-step for complete beginners:**

### 1. Sign Up
- Go to https://infinityfree.net
- Click "Sign Up"
- Choose a subdomain name (e.g., `mytelehealth.rf.gd`)
- Complete registration

### 2. Upload Files
- Login to control panel
- Go to "File Manager"
- Navigate to `htdocs` folder
- Upload all your project files (or use ZIP upload)

### 3. Create Database
- Go to "MySQL Databases"
- Click "Create Database"
- Note down: Database name, Username, Password

### 4. Import Database
- Go to "phpMyAdmin"
- Select your database
- Click "Import"
- Upload `database/complete_telehealth_db.sql`
- Click "Go"

### 5. Update Configuration
- Edit `config.php` in File Manager
- Update database credentials:
  ```php
  define('DB_HOST', 'sql123.infinityfree.com'); // Your DB host
  define('DB_USER', 'if0_12345678');             // Your DB username
  define('DB_PASS', 'your_password');            // Your DB password
  define('DB_NAME', 'if0_12345678_telehealth'); // Your DB name
  ```

### 6. Test Your Site
- Visit: `http://yoursubdomain.rf.gd`
- Login with default credentials from sample data
- Test all features

---

## ⚙️ Configuration Changes for Deployment

### 1. Update config.php

```php
<?php
// Database Configuration
define('DB_HOST', 'your_host');    // Change this
define('DB_USER', 'your_user');    // Change this
define('DB_PASS', 'your_pass');    // Change this
define('DB_NAME', 'your_dbname');  // Change this

// Base URL (IMPORTANT!)
define('BASE_URL', 'https://yoursite.com/'); // Change this

// Enable error logging (disable display for security)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log('/path/to/error.log');
?>
```

### 2. Create .htaccess for Security

```apache
# Create/update .htaccess in root
<Files "config.php">
    Order Allow,Deny
    Deny from all
</Files>

# Prevent directory listing
Options -Indexes

# Enable HTTPS redirect
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 3. Secure Your Database

After deployment:
1. Change default admin password
2. Delete test/sample users
3. Update all default credentials

```sql
-- Login to phpMyAdmin and run:
UPDATE users SET password = MD5('new_secure_password') WHERE email = 'admin@telehealth.com';
DELETE FROM users WHERE email LIKE '%test%';
```

---

## 🎥 WebRTC Video Calls Requirement

**IMPORTANT:** Video calls require HTTPS!

**Free HTTPS Options:**
1. **Railway/Render** - HTTPS included ✅
2. **InfinityFree** - Can add Cloudflare SSL (free) ✅
3. **Custom domain** - Use Cloudflare for free SSL ✅

**Add Cloudflare SSL to InfinityFree:**
1. Sign up at https://cloudflare.com (free)
2. Add your site
3. Update nameservers at InfinityFree
4. Enable "Flexible SSL" in Cloudflare
5. Wait 24 hours for activation

---

## 📊 Comparison Table

| Feature | InfinityFree | Railway | 000webhost | Render |
|---------|-------------|---------|------------|--------|
| **Price** | Free | $5 credits/mo | Free | Free |
| **Storage** | 5GB | Unlimited | 300MB | Unlimited |
| **Bandwidth** | Unlimited | Unlimited | 3GB | Unlimited |
| **HTTPS** | Via Cloudflare | ✅ Included | Via Cloudflare | ✅ Included |
| **Database** | MySQL | MySQL | MySQL | PostgreSQL |
| **Performance** | ⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐ |
| **Ease of Use** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ |
| **WebRTC Support** | With SSL | ✅ | With SSL | ✅ |
| **Ads** | No | No | Yes | No |

---

## 🎯 Recommended Deployment Path

**For Demo/Testing:**
→ Use **InfinityFree** (easiest, free, no credit card)

**For Production/Real Use:**
→ Use **Railway** (best performance, HTTPS, professional)

**For Learning/Portfolio:**
→ Use **Render** (modern, Git-based, free tier)

---

## 🛠️ Post-Deployment Checklist

- [ ] Database imported successfully
- [ ] config.php updated with correct credentials
- [ ] HTTPS enabled (for video calls)
- [ ] Admin password changed
- [ ] Test user registration
- [ ] Test appointment booking
- [ ] Test video calls (with HTTPS)
- [ ] Test prescription creation
- [ ] Test password reset emails (configure SMTP)
- [ ] Remove sample/test data
- [ ] Set up backups

---

## 📧 Email Configuration (Optional)

For password reset emails, configure SMTP in your code:

```php
// Use free SMTP services:
// 1. Gmail SMTP (requires app password)
// 2. SendGrid (100 emails/day free)
// 3. Mailgun (100 emails/day free)
```

---

## 🆘 Troubleshooting

**Database Connection Failed:**
- Double-check DB credentials in config.php
- Ensure DB host is correct (not always 'localhost')
- Verify database user has permissions

**Video Calls Not Working:**
- Ensure HTTPS is enabled
- Check WebRTC requires SSL/TLS
- Test on different browsers

**500 Internal Server Error:**
- Check PHP error logs
- Verify all required PHP extensions
- Check file permissions (755 for folders, 644 for files)

**Session Issues:**
- Check session.save_path permissions
- Verify PHP session settings on hosting

---

## 🚀 Quick Deploy Commands (Railway)

```bash
# 1. Install Railway CLI
npm i -g @railway/cli

# 2. Login
railway login

# 3. Initialize
railway init

# 4. Link to project
railway link

# 5. Add MySQL
railway add -d mysql

# 6. Deploy
railway up
```

---

## 📝 Need Custom Domain?

**Free Domain Options:**
1. **Freenom** - Free .tk, .ml, .ga domains (1 year)
2. **Dot.tk** - Free .tk domains
3. **GitHub Student Pack** - Free .me domain (if student)

**Connect Custom Domain:**
1. Point domain to hosting (A record or CNAME)
2. Update BASE_URL in config.php
3. Enable SSL via Cloudflare

---

## ✅ Ready to Deploy?

Choose your platform and follow the steps above. Start with **InfinityFree** if you're unsure - it's the easiest!

Need help? Check the troubleshooting section or reach out to the hosting provider's support.

**Good luck! 🎉**
