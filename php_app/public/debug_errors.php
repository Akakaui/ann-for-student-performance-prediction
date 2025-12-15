<?php
// Enable all error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Test basic PHP
echo "PHP is working!<br>";

// Test database connection
try {
    require_once '../lib/config.php';
    require_once '../lib/database.php';
    $db = Database::getInstance();
    echo "Database connection successful!<br>";
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}

// Test session
session_start();
echo "Session started!<br>";
?>