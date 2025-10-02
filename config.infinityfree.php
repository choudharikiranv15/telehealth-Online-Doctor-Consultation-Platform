<?php
// ============================================
// InfinityFree Deployment Configuration
// ============================================
// Copy this content to config.php when deploying to InfinityFree

// Database configuration - UPDATE THESE WITH YOUR INFINITYFREE CREDENTIALS
// You can find these in your InfinityFree Control Panel > MySQL Databases
define('DB_HOST', 'sql123.infinityfree.com');          // ⬅️ CHANGE: Your MySQL hostname
define('DB_NAME', 'if0_12345678_telehealth');          // ⬅️ CHANGE: Your database name
define('DB_USER', 'if0_12345678');                     // ⬅️ CHANGE: Your database username
define('DB_PASS', 'YOUR_DATABASE_PASSWORD');           // ⬅️ CHANGE: Your database password
define('DB_CHARSET', 'utf8mb4');

// Application configuration
define('SITE_NAME', 'iHealth MediCare');
define('SITE_URL', 'http://yoursubdomain.rf.gd');     // ⬅️ CHANGE: Your InfinityFree subdomain
define('ADMIN_EMAIL', 'admin@ihealth.com');

// --- START: INCLUDE HELPER FUNCTIONS ---
require_once __DIR__ . '/includes/helpers.php';
// --- END: INCLUDE HELPER FUNCTIONS ---

// Session configuration
define('SESSION_TIMEOUT', 3600); // 1 hour

// File upload configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', __DIR__ . '/assets/images/');

// Error reporting - PRODUCTION MODE (hide errors from users)
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Don't show errors to users
ini_set('log_errors', 1);      // Log errors to file
ini_set('error_log', __DIR__ . '/error.log');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
