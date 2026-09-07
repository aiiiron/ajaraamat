<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['family_id'])) {
    header('Location: login.php');
    exit;
}

function current_family_id(): int {
    return (int) $_SESSION['family_id'];
}
