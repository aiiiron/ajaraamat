<?php
// Demo login — NOT a public one-tap bypass. This is an ordinary login form
// that only succeeds for the specific DEMO_PARENT_EMAIL / DEMO_PARENT_PASSWORD
// configured in config.php (never committed to git); anyone else gets "vale
// e-post või parool", same as the real login form.
//
// Safety: this must never silently overwrite an already-logged-in REAL
// account's session (that's exactly what a shared PHPSESSID would otherwise
// do). If the visitor is currently logged in as themselves, they have to
// explicitly log out first before switching into the demo.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
configure_session();

if (!empty($_SESSION['family_id']) && empty($_SESSION['is_demo'])) {
    ?>
    <!DOCTYPE html>
    <html lang="et">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Proovi demot — Ajaraamat</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>">
    </head>
    <body class="centered">
    <div class="card login-card">
        <h2>Oled juba sisse logitud</h2>
        <p style="margin-top:8px;color:var(--text-muted);">Demo vaatamiseks pead esmalt oma kontost välja logima — muidu kirjutaks see su praeguse sisselogimise üle.</p>
        <a href="logout.php?then=demo" class="btn btn-add full-width" style="margin-top:16px;display:block;text-align:center;">Logi välja ja ava demo</a>
        <a href="paren.php" class="link-muted login-home-link">← Tagasi minu kontole</a>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $demoEmail = defined('DEMO_PARENT_EMAIL') ? (string) DEMO_PARENT_EMAIL : '';
    if ($demoEmail === '' || $email !== $demoEmail) {
        $error = 'Vale e-post või parool.';
    } else {
        $auth = authenticate_parent($email, $password);
        if (!$auth || $auth['status'] !== 'approved') {
            $error = 'Vale e-post või parool.';
        } else {
            session_regenerate_id(true);
            unset($_SESSION['is_admin']);
            $_SESSION['family_id']   = $auth['family_id'];
            $_SESSION['login_kind']  = $auth['kind'];
            $_SESSION['login_id']    = $auth['login_id'];
            $_SESSION['login_email'] = $auth['email'];
            $_SESSION['is_demo']     = 1;
            header('Location: paren.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Proovi demot — Ajaraamat</title>
<link rel="icon" href="favicon.ico?v=2" sizes="any">
<link rel="icon" href="icon-192.png?v=2" type="image/png">
<link rel="apple-touch-icon" href="apple-touch-icon.png?v=2">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#8B5CF6">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>">
</head>
<body class="centered">
<div class="card login-card">
    <div class="brand-logo-full"><img src="logo-full.png" alt="Ajaraamat" width="240"></div>
    <h2>Demo — vanema vaade</h2>
    <p style="margin:4px 0 14px;color:var(--text-muted);font-size:14px;">Küsi demo sisselogimisandmed saidi omanikult.</p>
    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post">
        <label for="email">E-post</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autofocus required>

        <label for="password">Parool</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" class="btn btn-add full-width">Logi sisse</button>
    </form>
    <a href="index.php" class="link-muted login-home-link">← Avaleht</a>
</div>
</body>
</html>
