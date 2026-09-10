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
header('Location: login.php');
exit;
