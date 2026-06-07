<?php
// Database configuration - Supabase (PostgreSQL)
// Set these in your server environment or .env file
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'postgres');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_SSLMODE', getenv('DB_SSLMODE') ?: 'require');

// Application configuration
define('APP_NAME', getenv('APP_NAME') ?: 'Student Performance Predictor');
define('APP_VERSION', getenv('APP_VERSION') ?: '1.0');
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/php_app/public/');

// Python configuration (use system python3 on Linux/server)
define('PYTHON_PATH', getenv('PYTHON_PATH') ?: 'python3');
define('MODEL_SCRIPT_PATH', getenv('MODEL_SCRIPT_PATH') ?: realpath(__DIR__ . '/../../python_model/scripts/predict.py'));

// Error reporting (disable in production)
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN));
error_reporting(APP_DEBUG ? E_ALL : 0);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

// Session configuration - MUST be set BEFORE session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', filter_var(getenv('SESSION_SECURE') ?: 'false', FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
ini_set('session.use_strict_mode', 1);

// Timezone
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Lagos');
?>