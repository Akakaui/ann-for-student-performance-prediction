<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'student_predictor');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('APP_NAME', 'Student Performance Predictor');
define('APP_VERSION', '1.0');
define('BASE_URL', 'http://localhost/student_predictor/php_app/public/');

// Python configuration
define('PYTHON_PATH', 'C:/Users/AKAKA/AppData/Local/Programs/Python/Python39/python.exe');
define('MODEL_SCRIPT_PATH', 'C:/wamp64/www/student_predictor/python_model/scripts/predict.py');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration - MUST be set BEFORE session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.use_strict_mode', 1);

// Timezone
date_default_timezone_set('Africa/Lagos');
?>