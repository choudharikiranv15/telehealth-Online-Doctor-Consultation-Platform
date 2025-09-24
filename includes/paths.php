<?php
// Path resolution helper

// It's good practice to wrap all function definitions in a check
if (!function_exists('getBaseUrl')) {
    function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $pathInfo = pathinfo($scriptName);
        $basePath = $pathInfo['dirname'];
        
        // If we're in a subdirectory, get the base path
        if (strpos($basePath, '/telehealth') !== false) {
            $basePath = '/telehealth';
        } else {
            $basePath = '';
        }
        
        return $protocol . '://' . $host . $basePath;
    }
}

if (!function_exists('getAssetUrl')) {
    function getAssetUrl($path) {
        return getBaseUrl() . '/assets/' . ltrim($path, '/');
    }
}

// --- START: FIX FOR "CANNOT REDECLARE" ERROR ---
// This check prevents the fatal error by only defining the function if it doesn't already exist.
if (!function_exists('getPageUrl')) {
    function getPageUrl($page) {
        return getBaseUrl() . '/' . ltrim($page, '/');
    }
}
// --- END: FIX ---

// --- It's also safer to check if constants are already defined ---
if (!defined('BASE_URL')) {
    define('BASE_URL', getBaseUrl());
}
if (!defined('ASSETS_URL')) {
    define('ASSETS_URL', getBaseUrl() . '/assets');
}
if (!defined('CSS_URL')) {
    define('CSS_URL', ASSETS_URL . '/css');
}
if (!defined('JS_URL')) {
    define('JS_URL', ASSETS_URL . '/js');
}
if (!defined('IMAGES_URL')) {
    define('IMAGES_URL', ASSETS_URL . '/images');
}
?>
