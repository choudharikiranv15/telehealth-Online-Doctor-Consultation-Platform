# 🚀 InfinityFree Deployment Guide - Step by Step

Complete guide to deploy your TeleHealth platform on InfinityFree for **FREE**.

---

## 📋 What You'll Need

- InfinityFree account (free)
- Your project files
- Database SQL file: `database/complete_telehealth_db.sql`
- 15-30 minutes

---

## Step 1: Create InfinityFree Account

1. Go to **https://infinityfree.net**
2. Click **"Sign Up"**
3. Enter your email and create password
4. Verify your email

✅ **Done!** You now have an InfinityFree account.

---

## Step 2: Create Your Website

1. **Login to InfinityFree Control Panel**
2. Click **"Create Account"**
3. Fill in the form:
   - **Username**: Choose a username (e.g., `mytelehealth`)
   - **Domain**: Choose option:
     - Use free subdomain (e.g., `mytelehealth.rf.gd`) ⭐ **Recommended**
     - Or use your own domain (if you have one)
   - **Password**: Create a strong password for this website
   - **Email**: Your email

4. Click **"Create Account"**

⏳ **Wait 2-5 minutes** for account activation.

✅ **Done!** Your hosting account is ready.

---

## Step 3: Get Your Database Credentials

After your account is activated:

1. Go to **Control Panel** (Cpanel)
2. Find **"MySQL Databases"** section
3. Click **"MySQL Databases"**

### Create Database:

4. Under **"Create New Database"**:
   - Database Name: `telehealth` (or any name you prefer)
   - Click **"Create Database"**

5. **IMPORTANT: Note down these details** (you'll need them later):

```
Database Host: sql123.infinityfree.com (or similar)
Database Name: if0_12345678_telehealth
Database Username: if0_12345678
Database Password: (the password you set)
```

💡 **Pro Tip:** Copy these to a text file - you'll need them in Step 5!

✅ **Done!** Database is created.

---

## Step 4: Import Database

1. In Control Panel, find **"phpMyAdmin"**
2. Click to open phpMyAdmin
3. Click on your database name (e.g., `if0_12345678_telehealth`) in left sidebar
4. Click **"Import"** tab at the top
5. Click **"Choose File"**
6. Select `database/complete_telehealth_db.sql` from your computer
7. Scroll down and click **"Go"**

⏳ **Wait** for import to complete (30-60 seconds)

✅ **Done!** Database tables are created with sample data.

**Verify:** You should see 15 tables in the left sidebar:
- users
- appointments
- doctor_profiles
- patient_profiles
- call_sessions
- prescriptions
- reviews
- payments
- (and 7 more...)

---

## Step 5: Update config.php

Now update your `config.php` file with InfinityFree credentials.

### Option A: Using File Manager (Easier)

1. Go to Control Panel → **"File Manager"**
2. Navigate to `htdocs` folder
3. Find `config.php` file
4. Right-click → **"Edit"**
5. Find these lines (around line 4-7):

```php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'telehealth_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
```

6. **Replace with your InfinityFree credentials:**

```php
define('DB_HOST', 'sql123.infinityfree.com');        // ⬅️ YOUR hostname from Step 3
define('DB_NAME', 'if0_12345678_telehealth');        // ⬅️ YOUR database name
define('DB_USER', 'if0_12345678');                   // ⬅️ YOUR username
define('DB_PASS', 'your_password_here');             // ⬅️ YOUR password
```

7. **Also update SITE_URL** (around line 13):

```php
define('SITE_URL', 'http://yoursubdomain.rf.gd');   // ⬅️ YOUR subdomain
```

8. Click **"Save Changes"**

### Option B: Using FTP (Alternative)

1. Download `config.php` from server
2. Edit with Notepad/VS Code
3. Update the same lines as above
4. Upload back to server

✅ **Done!** Configuration is updated.

---

## Step 6: Upload Project Files

### Using File Manager:

1. Go to Control Panel → **"File Manager"**
2. Navigate to `htdocs` folder
3. Delete default files (`default.php`, `index.php` if present)

4. **Upload your files:**
   - Option A: Upload individual files/folders
   - Option B: ZIP your project → Upload ZIP → Extract

5. **Folder structure should look like:**
   ```
   htdocs/
   ├── admin/
   ├── api/
   ├── assets/
   ├── database/
   ├── doctor/
   ├── includes/
   ├── patient/
   ├── config.php
   ├── index.php
   ├── login.php
   ├── register.php
   ├── .htaccess
   └── (other files...)
   ```

✅ **Done!** Files are uploaded.

---

## Step 7: Set File Permissions

1. In File Manager, right-click on `assets/images` folder
2. Select **"Change Permissions"**
3. Set to **755** (or check: Owner Read/Write/Execute, Group/Public Read/Execute)
4. Check **"Recurse into subdirectories"**
5. Click **"Change Permissions"**

✅ **Done!** Upload folder has correct permissions.

---

## Step 8: Enable HTTPS (SSL) - Optional but Recommended

For video calls to work properly, you need HTTPS.

### Free SSL with Cloudflare:

1. Sign up at **https://cloudflare.com** (free)
2. Add your site (e.g., `yoursubdomain.rf.gd`)
3. Cloudflare will give you 2 nameservers
4. In InfinityFree Control Panel:
   - Go to **"Account Settings"**
   - Find **"Nameservers"**
   - Update with Cloudflare nameservers
5. In Cloudflare dashboard:
   - Go to **SSL/TLS** settings
   - Choose **"Flexible"** SSL mode
6. Wait 24 hours for SSL to activate

After SSL is active, update `config.php`:
```php
define('SITE_URL', 'https://yoursubdomain.rf.gd');  // Changed http → https
```

And enable HTTPS redirect in `.htaccess`:
- Uncomment the HTTPS redirect lines (remove the `#` symbols)

✅ **Done!** HTTPS is enabled.

---

## Step 9: Test Your Website

1. **Open your website**: `http://yoursubdomain.rf.gd` (or `https://` if SSL enabled)

2. **Test homepage loads** without errors

3. **Login with default admin account:**
   - Email: Check your SQL file for admin email (usually `admin@telehealth.com`)
   - Password: Check your SQL file (default from sample data)

4. **Test features:**
   - ✅ Doctor listing shows
   - ✅ Registration works (create test patient)
   - ✅ Login/Logout works
   - ✅ Dashboard displays
   - ✅ Profile pictures upload
   - ✅ Appointment booking works

5. **IMPORTANT: Change admin password immediately!**
   - Login as admin
   - Go to Profile → Change Password

✅ **Done!** Website is live and working!

---

## Step 10: Post-Deployment Security

### Change Admin Password:

1. Login to phpMyAdmin
2. Select your database
3. Click on `users` table
4. Find the admin user row
5. Click **"Edit"**
6. Change password field to: `MD5('your_new_secure_password')`
   - Or in the password field, select **MD5** from Function dropdown, then enter your new password
7. Click **"Go"**

### Delete Test Users:

1. Review all users in `users` table
2. Delete any test accounts you don't need

✅ **Done!** Your site is secure!

---

## 📊 Your InfinityFree Configuration Summary

After setup, your `config.php` should look like:

```php
<?php
// Database configuration
define('DB_HOST', 'sql123.infinityfree.com');        // Your host
define('DB_NAME', 'if0_12345678_telehealth');        // Your database
define('DB_USER', 'if0_12345678');                   // Your username
define('DB_PASS', 'your_password');                  // Your password
define('DB_CHARSET', 'utf8mb4');

// Application configuration
define('SITE_NAME', 'iHealth MediCare');
define('SITE_URL', 'https://yoursubdomain.rf.gd');  // Your URL
define('ADMIN_EMAIL', 'admin@ihealth.com');

// ... rest of the file stays the same
?>
```

---

## 🆘 Troubleshooting

### "Database connection failed"
**Solution:**
- Double-check DB credentials in `config.php`
- Verify you copied the exact values from Step 3
- Check DB_HOST is correct (not 'localhost')
- Make sure database was created in Step 3

### "404 Not Found"
**Solution:**
- Verify files are in `htdocs` folder (not in a subfolder)
- Check file names are correct (case-sensitive)
- Clear browser cache

### "500 Internal Server Error"
**Solution:**
- Check `.htaccess` file syntax
- Verify PHP version compatibility
- Check `error.log` file in your hosting for details
- Try renaming `.htaccess` temporarily to test

### "Images not uploading"
**Solution:**
- Check `assets/images` folder permissions (755)
- Verify folder exists on server
- Check InfinityFree upload limits

### "Video calls not working"
**Solution:**
- Enable HTTPS (Step 8) - WebRTC requires SSL
- Test in Chrome or Firefox
- Check browser permissions for camera/microphone

### "Cannot access config.php" (Good!)
This is **normal** and **correct** - `.htaccess` is protecting this file from direct access.

---

## 📝 InfinityFree Limitations (Free Tier)

Be aware of these limitations:

- **Request Limit**: ~50,000 hits per day
- **CPU Usage**: Limited (if exceeded, site may be suspended temporarily)
- **File Size**: 10MB max per file
- **Storage**: 5GB total
- **Bandwidth**: Unlimited but may throttle if excessive
- **Inodes**: 30,000 files limit
- **No Email**: Cannot send emails (use external SMTP like Gmail)

💡 **These limits are generous for a demo/testing site!**

---

## 🎯 What's Next?

### For Production Use:
If your site becomes popular and hits InfinityFree limits, consider upgrading to:
- **InfinityFree Premium** (paid)
- **Railway.app** ($5/month credit)
- **DigitalOcean** ($5/month)
- **Hostinger** ($2-3/month)

### Custom Domain:
- Get free domain: Freenom.com (.tk, .ml domains)
- Get paid domain: Namecheap, GoDaddy ($10/year)
- Connect to InfinityFree in Control Panel → Domain Settings

### Enable Email Notifications:
- Use external SMTP: Gmail, SendGrid, Mailgun
- Update code to send emails via SMTP

---

## ✅ Deployment Checklist

Quick checklist for InfinityFree deployment:

```
□ InfinityFree account created
□ Website created with subdomain
□ Database created
□ Database credentials noted down
□ SQL file imported via phpMyAdmin
□ config.php updated with credentials
□ SITE_URL updated in config.php
□ All project files uploaded to htdocs
□ File permissions set (755 for assets/images)
□ Website loads without errors
□ Admin password changed
□ Test users removed
□ SSL enabled via Cloudflare (optional)
□ All features tested
```

---

## 🎉 Congratulations!

Your TeleHealth platform is now **LIVE** on InfinityFree!

**Your live site:** `http://yoursubdomain.rf.gd`

Share it with friends, test all features, and enjoy your free hosting!

---

## 📞 Need Help?

- **InfinityFree Forum**: https://forum.infinityfree.net
- **InfinityFree Knowledge Base**: https://infinityfree.net/support
- **Check your error.log** file for debugging

---

**Deployment completed! Time to show off your project! 🚀**
