<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';

session_start();

// Redirect to dashboard if already logged in
if (Utils::isLoggedIn()) {
    Utils::redirect('dashboard.php');
} else {
    Utils::redirect('login.php');
}
?>