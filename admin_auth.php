<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_admin()) {
    // A logged-in family that isn't the owner goes back to its dashboard;
    // anyone else to the (still-supported) ADMIN_PASSWORD login.
    header('Location: ' . (!empty($_SESSION['family_id']) ? 'paren.php' : 'admin_login.php'));
    exit;
}
