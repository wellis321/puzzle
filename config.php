<?php
/**
 * Application Configuration
 * Loads settings from .env file
 */

require_once __DIR__ . '/includes/EnvLoader.php';

// Load environment variables from .env file
EnvLoader::load(__DIR__ . '/.env');

// Database Configuration
define('DB_HOST', EnvLoader::get('DB_HOST', 'localhost'));
define('DB_NAME', EnvLoader::get('DB_NAME', 'mystery_puzzle'));
define('DB_USER', EnvLoader::get('DB_USER', 'root'));
define('DB_PASS', EnvLoader::get('DB_PASS', 'root'));
define('DB_PORT', EnvLoader::get('DB_PORT', '8889'));

// Application Settings
define('APP_NAME', EnvLoader::get('APP_NAME', 'Daily Mystery'));
define('APP_URL', EnvLoader::get('APP_URL', 'http://localhost:8888/puzzle'));

// Admin Settings
define('ADMIN_USERNAME', EnvLoader::get('ADMIN_USERNAME', 'admin'));

// Hash the password from .env if it's not already hashed
$adminPassword = EnvLoader::get('ADMIN_PASSWORD', 'changeme123');
// Check if password is already hashed (bcrypt hashes start with $2y$)
if (strpos($adminPassword, '$2y$') === 0) {
    define('ADMIN_PASSWORD', $adminPassword);
} else {
    define('ADMIN_PASSWORD', password_hash($adminPassword, PASSWORD_DEFAULT));
}

// Timezone
date_default_timezone_set(EnvLoader::get('TIMEZONE', 'America/New_York'));

// Stripe Configuration
define('STRIPE_PUBLIC_KEY', EnvLoader::get('STRIPE_PUBLIC_KEY', ''));
define('STRIPE_SECRET_KEY', EnvLoader::get('STRIPE_SECRET_KEY', ''));
define('STRIPE_WEBHOOK_SECRET', EnvLoader::get('STRIPE_WEBHOOK_SECRET', ''));

// Ad Configuration
define('ENABLE_ADS', EnvLoader::get('ENABLE_ADS', 'true') === 'true');
define('AD_PROVIDER', EnvLoader::get('AD_PROVIDER', 'adsense'));
define('ADSENSE_CLIENT_ID', EnvLoader::get('ADSENSE_CLIENT_ID', ''));

// Error Reporting (based on environment)
$appEnv = EnvLoader::get('APP_ENV', 'development');
if ($appEnv === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
