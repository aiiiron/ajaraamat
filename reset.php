<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
configure_session();

// Token comes from the query string on GET, from a hidden field on POST.
$token = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['token'] ?? '')
    : ($_GET['token'] ?? '');
$token = is_string($token) ? trim($token) : '';

$reset   = find_valid_password_reset($token);
$invalid = ($reset === null);
$error   = '';
$done    = false;

if (!$invalid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $pw  = $_POST['password'] ?? '';
    $pwc = $_POST['password_confirm'] ?? '';

    if (strlen($pw) < 6) {
        $error = 'Parool peab olema vähemalt 6 tähemärki.';
    } elseif ($pw !== $pwc) {
        $error = 'Paroolid ei kattu.';
    } else {
        complete_password_reset((int) $reset['id'], (int) $reset['family_id'], $pw);
        // A successful reset clears any login lockout stuck on this browser.
        unset($_SESSION['login_fails'], $_SESSION['login_lock_until']);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Uus parool — Ajaraamat</title>
<link rel="icon" href="favicon.ico?v=2" sizes="any">
<link rel="icon" href="icon-192.png?v=2" type="image/png">
<link rel="apple-touch-icon" href="apple-touch-icon.png?v=2">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#8B5CF6">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Ajaraamat">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>">
</head>
<body class="centered">
<div class="card login-card">
    <div class="brand-logo-full"><img src="logo-full.png" alt="Ajaraamat" width="240"></div>

    <?php if ($done): ?>
        <p style="margin:16px 0;">Parool on uuendatud. Nüüd saad uue parooliga sisse logida.</p>
        <a href="login.php" class="link-muted login-home-link">Logi sisse →</a>
    <?php elseif ($invalid): ?>
        <p class="error">See link on aegunud või juba kasutatud.</p>
        <p style="margin:16px 0;font-size:14px;color:var(--text-muted);">Küsi uus lähtestamise link.</p>
        <a href="forgot.php" class="link-muted login-home-link">← Uus link</a>
    <?php else: ?>
        <p style="color:var(--text-muted);font-size:14px;margin-bottom:16px;">Vali oma kontole uus parool.</p>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">

            <label for="password">Uus parool</label>
            <input type="password" id="password" name="password" required minlength="6" autofocus>

            <label for="password_confirm">Uus parool uuesti</label>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="6">

            <button type="submit" class="btn btn-add full-width">Salvesta uus parool</button>
        </form>
        <a href="login.php" class="link-muted login-home-link">← Tagasi sisselogimisse</a>
    <?php endif; ?>
</div>
</body>
</html>
