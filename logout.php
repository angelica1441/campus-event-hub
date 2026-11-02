<?php
require_once __DIR__ . '/config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Clear all session variables.
$_SESSION = [];

// Destroy the current session.
session_destroy();

// Prevent the browser from reusing cached authenticated pages.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Redirect to the login page after logout.
header('Location: login.php');
exit;