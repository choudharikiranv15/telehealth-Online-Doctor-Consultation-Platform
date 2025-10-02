<?php
// Database configuration
// For deployment, update these values with your hosting provider's database credentials
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'telehealth_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Application configuration
define('SITE_NAME', 'iHealth MediCare');
// IMPORTANT: Update SITE_URL when deploying (e.g., 'https://yourdomain.com' or 'https://yoursubdomain.rf.gd')
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/telehealth');
define('ADMIN_EMAIL', 'admin@ihealth.com');

// --- START: INCLUDE HELPER FUNCTIONS ---
// This line loads the custom helper functions and makes them available globally.
// This will fix the "Call to undefined function getPageUrl()" error.
require_once __DIR__ . '/includes/helpers.php';
// --- END: INCLUDE HELPER FUNCTIONS ---

// Session configuration
define('SESSION_TIMEOUT', 3600); // 1 hour

// File upload configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
// Use __DIR__ to create a robust, absolute path for uploads.
define('UPLOAD_PATH', __DIR__ . '/assets/images/');

// Error reporting
// For PRODUCTION: Disable display_errors and log errors instead
$is_production = getenv('APP_ENV') === 'production';
if ($is_production) {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log');
} else {
    // For DEVELOPMENT: Show all errors
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
