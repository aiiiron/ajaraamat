<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
configure_session();

// Already logged in? Nothing to reset.
if (!empty($_SESSION['family_id'])) {
    header('Location: paren.php');
    exit;
}

$error = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $now = time();
    $lockUntil = (int) ($_SESSION['forgot_lock_until'] ?? 0);

    if ($now < $lockUntil) {
        $error = 'Liiga palju katseid. Proovi ' . ($lockUntil - $now) . ' sekundi pärast uuesti.';
    } else {
        // Same session-based throttle style as login.php: 5 requests, then a
        // 5-minute lock. Deliberately coarse — this endpoint sends e-mail.
        $_SESSION['forgot_fails'] = (int) ($_SESSION['forgot_fails'] ?? 0) + 1;
        if ($_SESSION['forgot_fails'] >= 5) {
            $_SESSION['forgot_lock_until'] = $now + 300;
            $_SESSION['forgot_fails'] = 0;
        }

        $email = trim($_POST['email'] ?? '');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $family = get_family_by_email($email);
            // Only approved accounts self-serve a reset; pending/rejected
            // accounts are still the site owner's call.
            if ($family && $family['status'] === 'approved') {
                $token = create_password_reset((int) $family['id']);
                $link = base_url() . '/reset.php?token=' . $token;
                send_password_reset_email($family['email'], $link);
            }
        }

        // Always the exact same outcome — never reveal whether an address
        // has an account.
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Unustasid parooli? — Ajaraamat</title>
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" href="icon-192.png" type="image/png">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
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
        <p style="margin:16px 0;">
            Kui selle e-postiga on kinnitatud konto, saatsime sinna parooli lähtestamise lingi.
            Vaata ka rämpsposti kausta. Link kehtib 24 tundi.
        </p>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
            Kirja ei tulnud? Võta ühendust saidi omanikuga — tema saab lingi sulle käsitsi anda.
        </p>
        <a href="login.php" class="link-muted login-home-link">← Tagasi sisselogimisse</a>
    <?php else: ?>
        <p style="color:var(--text-muted);font-size:14px;margin-bottom:16px;">
            Sisesta oma konto e-post. Saadame sinna lingi, millega saad uue parooli valida.
        </p>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label for="email">E-post</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autofocus required>

            <button type="submit">Saada lähtestamise link</button>
        </form>
        <a href="login.php" class="link-muted login-home-link">← Tagasi sisselogimisse</a>
    <?php endif; ?>
</div>
</body>
</html>
