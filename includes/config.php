<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'campusbuzz');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHAR', 'utf8mb4');

// Site Configuration
define('SITE_NAME', 'CampusBuzz');
define('BASE_URL', '/newsdl/');

// Error Settings - Enable error display for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Session Settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_name('CAMPUSBUZZ_SESSION');

// Time Zone
date_default_timezone_set('UTC'); 