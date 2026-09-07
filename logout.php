<?php
session_start();
unset($_SESSION['family_id']);
session_destroy();
header('Location: login.php');
exit;
