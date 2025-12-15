<?php
session_start();
echo "<pre>";
echo "Session Data:\n";
print_r($_SESSION);
echo "\nCookies:\n";
print_r($_COOKIE);
echo "\nSession ID: " . session_id();
echo "</pre>";
?>