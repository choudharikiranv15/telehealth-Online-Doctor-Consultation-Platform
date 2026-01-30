<?php
// Metered Video API Configuration
// Sign up at https://www.metered.ca/ to get your domain

// Your Metered domain (replace with your actual domain)
define('METERED_DOMAIN', 'ihealth.metered.live');

// Optional: Your Metered API key for advanced features
// Set this via environment variable or replace with your actual key
define('METERED_API_KEY', getenv('METERED_API_KEY') ?: 'YOUR_METERED_API_KEY_HERE');

// Video call settings
define('METERED_MAX_PARTICIPANTS', 2); // For 1-on-1 consultations
define('METERED_DEFAULT_AUDIO', true);
define('METERED_DEFAULT_VIDEO', true);
define('METERED_RECORDING_ENABLED', false); // Set to true if you want to record sessions

// Room settings
define('METERED_ROOM_PREFIX', 'ihealth_');
define('METERED_ROOM_TIMEOUT', 3600); // 1 hour in seconds

return [
    'domain' => METERED_DOMAIN,
    'api_key' => METERED_API_KEY,
    'max_participants' => METERED_MAX_PARTICIPANTS,
    'default_audio' => METERED_DEFAULT_AUDIO,
    'default_video' => METERED_DEFAULT_VIDEO,
    'recording_enabled' => METERED_RECORDING_ENABLED,
    'room_prefix' => METERED_ROOM_PREFIX,
    'room_timeout' => METERED_ROOM_TIMEOUT
];
?>