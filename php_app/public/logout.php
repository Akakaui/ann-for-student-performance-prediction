<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/utils.php';

session_start();

$auth = new Auth();
$auth->logout();

$_SESSION['success'] = "You have been logged out successfully.";
Utils::redirect('login.php');
?>