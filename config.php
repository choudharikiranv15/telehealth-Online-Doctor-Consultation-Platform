<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'telehealth_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application configuration
define('SITE_NAME', 'iHealth MediCare');
define('SITE_URL', 'http://localhost/telehealth');
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

// Error reporting (for development only)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
