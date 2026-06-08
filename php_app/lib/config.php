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
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/php_errors.log');

// Session configuration - MUST be set BEFORE session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', filter_var(getenv('SESSION_SECURE') ?: 'false', FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
ini_set('session.use_strict_mode', 1);

// Timezone
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Lagos');

// Global error handler for graceful errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal error: " . $error['message'] . " in " . $error['file'] . ":" . $error['line']);
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><title>Error</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body style="background:linear-gradient(135deg,#667eea,#764ba2);min-height:100vh;display:flex;align-items:center"><div class="container"><div class="row justify-content-center"><div class="col-md-6"><div class="card shadow text-center p-5" style="border-radius:15px"><h2>Something went wrong</h2><p class="text-muted">We encountered an error. Please try again.</p><a href="login.php" class="btn btn-primary">Go to Login</a></div></div></div></div></body></html>';
        exit;
    }
});
?>