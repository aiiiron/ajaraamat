<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $now = time();
    $lockUntil = (int) ($_SESSION['admin_lock_until'] ?? 0);

    if ($now < $lockUntil) {
        $error = 'Liiga palju ebaõnnestunud katseid. Proovi hiljem uuesti.';
    } elseif (hash_equals(ADMIN_PASSWORD, (string) ($_POST['password'] ?? ''))) {
        unset($_SESSION['admin_fails'], $_SESSION['admin_lock_until']);
        $_SESSION['is_admin'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $_SESSION['admin_fails'] = (int) ($_SESSION['admin_fails'] ?? 0) + 1;
        if ($_SESSION['admin_fails'] >= 5) {
            $_SESSION['admin_lock_until'] = $now + 120;
            $_SESSION['admin_fails'] = 0;
        }
        $error = 'Vale parool.';
    }
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Ajaraamat</title>
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>">
</head>
<body class="centered">
<div class="card login-card">
    <h1>🛠 Admin</h1>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post">
        <label for="password">Admini parool</label>
        <input type="password" id="password" name="password" autofocus required>
        <button type="submit">Logi sisse</button>
    </form>
    <a href="index.php" class="link-muted login-home-link">← Avaleht</a>
</div>
</body>
</html>
