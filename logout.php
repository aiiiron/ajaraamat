<?php
session_start();
unset(
    $_SESSION['family_id'],
    $_SESSION['login_kind'],
    $_SESSION['login_id'],
    $_SESSION['login_email'],
    $_SESSION['is_demo']
);
session_destroy();
$dest = ($_GET['then'] ?? '') === 'demo' ? 'demo_parent.php' : 'login.php';
header('Location: ' . $dest);
exit;
