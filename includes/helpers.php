<?php
/**
 * Core Helper Functions
 *
 * This file contains globally available helper functions for the application.
 */

// Ensure the SITE_URL constant is defined before using it.
if (!defined('SITE_URL')) {
    // Fallback in case config.php was not loaded, though it should be.
    define('SITE_URL', 'http://localhost/telehealth');
}

/**
 * Generates a full, absolute URL for a given page within the application.
 *
 * This function uses the SITE_URL constant from config.php to ensure all links
 * are consistent and correct, regardless of the current directory.
 *
 * @param string $page The relative path to the page (e.g., 'login.php', 'patient/dashboard.php').
 * @return string The full, absolute URL.
 */

// --- START: FIX FOR "CANNOT REDECLARE" ERROR ---
// We wrap the function definition in a check to see if it already exists.
// This prevents a fatal error if another file (like paths.php) also defines it.
if (!function_exists('getPageUrl')) {
    function getPageUrl($page) {
        // Use rtrim to remove any trailing slash from SITE_URL, and ltrim to remove any leading slash from $page
        // to prevent double slashes in the final URL.
        return rtrim(SITE_URL, '/') . '/' . ltrim($page, '/');
    }
}
// --- END: FIX ---

// You can add other helper functions here in the future.
?>

