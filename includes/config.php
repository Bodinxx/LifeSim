<?php
/**
 * LifeSim — site-wide configuration
 */

define('LIFESIM_VERSION', '0.1.0');
define('SAVE_FORMAT_VERSION', 1);

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH', ROOT_PATH . '/data');
define('ADMIN_PATH', ROOT_PATH . '/admin');

// Admin credentials file (stores hashed password + change flag)
define('ADMIN_CREDENTIALS_FILE', DATA_PATH . '/admin_credentials.json');

// Admin change log file
define('ADMIN_LOG_FILE', DATA_PATH . '/admin_log.json');

// Events data file
define('EVENTS_FILE', DATA_PATH . '/events.json');

// Default admin username
define('ADMIN_DEFAULT_USERNAME', 'admin');

// Default admin password (used only to seed credentials file if it does not exist)
define('ADMIN_DEFAULT_PASSWORD', 'admin');

// Session settings
define('SESSION_NAME', 'lifesim_admin');
define('SESSION_LIFETIME', 3600); // seconds

// Time zone
date_default_timezone_set('UTC');
