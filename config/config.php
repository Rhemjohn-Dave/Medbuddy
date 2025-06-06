<?php
// Application configuration

// Email settings
define('ADMIN_EMAIL', 'admin@medbuddy.com');

// Application settings
define('APP_NAME', 'MedBuddy');
define('APP_URL', 'http://localhost/Medbuddy');

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-error.log'); 