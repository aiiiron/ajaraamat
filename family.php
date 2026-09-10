<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$familyId = current_family_id();
$family = get_family($familyId);
$isDemo = is_demo_session();

$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($isDemo) {
        $error = 'Demo režiimis ei saa kontosid muuta.';
    } elseif ($action === 'add_login') {
        $email = trim($_POST['email'] ?? '');
        $name  = trim($_POST['name'] ?? '');
        $pw    = $_POST['password'] ?? '';
        $pw2   = $_POST['password_confirm'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Sisesta kehtiv e-posti aadress.';
        } elseif (strlen($pw) < 6) {
            $error = 'Parool peab olema vähemalt 6 tähemärki.';
        } elseif ($pw !== $pw2) {
            $error = 'Paroolid ei kattu.';
        } elseif (parent_email_exists($email)) {
            $error = 'See e-post on juba kasutusel.';
        } else {
            add_family_login($familyId, $email, $name, $pw);
            header('Location: family.php?added=1');
            exit;
        }
    } elseif ($action === 'del_login') {
        delete_family_login((int) ($_POST['login_id'] ?? 0), $familyId);
        header('Location: family.php?removed=1');
        exit;
    } elseif ($action === 'change_pw') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $new2 = $_POST['new_password_confirm'] ?? '';
        if (strlen($new) < 6) {
            $error = 'Uus parool peab olema vähemalt 6 tähemärki.';
        } elseif ($new !== $new2) {
            $error = 'Uued paroolid ei kattu.';
        } elseif (!change_current_password($cur, $new)) {
            $error = 'Praegune parool on vale.';
        } else {
            header('Location: family.php?pw=1');
            exit;
        }
    }
}

if (($_GET['added'] ?? '') === '1')   $notice = 'Teine vanem lisatud. Anna talle e-post ja parool ise edasi.';
if (($_GET['removed'] ?? '') === '1') $notice = 'Vanema ligipääs eemaldatud.';
if (($_GET['pw'] ?? '') === '1')      $notice = 'Parool muudetud.';

$logins = get_family_logins($familyId);
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Pere ja vanemad — Ajaraamat</title>
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
<body>
<div class="wrap narrow">
    <header class="topbar">
        <a href="children.php" class="link-muted"><?= icon("arrow-left") ?> Tagasi</a>
    </header>

    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($notice): ?><div class="milestone-banner"><?= icon("check") ?> <?= htmlspecialchars($notice) ?></div><?php endif; ?>

    <div class="card">
        <h2>Vanemate ligipääs</h2>
        <p class="goal-form-note" style="margin:0 0 14px;">Igal vanemal on oma e-post ja parool, aga kõik näevad ja saavad muuta sama pere andmeid.</p>

        <div class="fl-row">
            <div class="fl-main">
                <div class="fl-email"><?= htmlspecialchars($family['email'] ?? '') ?></div>
                <div class="fl-tag">Peakonto<?= current_login_email() === ($family['email'] ?? '') ? ' · sina' : '' ?></div>
            </div>
        </div>
        <?php foreach ($logins as $l): ?>
        <div class="fl-row">
            <div class="fl-main">
                <div class="fl-email"><?= htmlspecialchars($l['email']) ?></div>
                <div class="fl-tag"><?= $l['name'] ? htmlspecialchars($l['name']) . ' · ' : '' ?>teine vanem<?= current_login_email() === $l['email'] ? ' · sina' : '' ?></div>
            </div>
            <?php if (!$isDemo): ?>
            <form method="post" onsubmit="return confirm('Eemaldada selle vanema ligipääs?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="del_login">
                <input type="hidden" name="login_id" value="<?= (int) $l['id'] ?>">
                <button type="submit" class="btn-reject">Eemalda</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2>Lisa teine vanem</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_login">
            <label for="fl_email">E-post</label>
            <input type="email" id="fl_email" name="email" required <?= $isDemo ? 'disabled' : '' ?>>
            <label for="fl_name">Nimi (valikuline)</label>
            <input type="text" id="fl_name" name="name" <?= $isDemo ? 'disabled' : '' ?>>
            <label for="fl_pw">Parool</label>
            <input type="password" id="fl_pw" name="password" required <?= $isDemo ? 'disabled' : '' ?>>
            <label for="fl_pw2">Parool uuesti</label>
            <input type="password" id="fl_pw2" name="password_confirm" required <?= $isDemo ? 'disabled' : '' ?>>
            <button type="submit" class="btn btn-add full-width" <?= $isDemo ? 'disabled' : '' ?>>Lisa vanem</button>
        </form>
    </div>

    <div class="card">
        <h2>Muuda oma parooli</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_pw">
            <label for="cp_cur">Praegune parool</label>
            <input type="password" id="cp_cur" name="current_password" required <?= $isDemo ? 'disabled' : '' ?>>
            <label for="cp_new">Uus parool</label>
            <input type="password" id="cp_new" name="new_password" required <?= $isDemo ? 'disabled' : '' ?>>
            <label for="cp_new2">Uus parool uuesti</label>
            <input type="password" id="cp_new2" name="new_password_confirm" required <?= $isDemo ? 'disabled' : '' ?>>
            <button type="submit" class="btn btn-add full-width" <?= $isDemo ? 'disabled' : '' ?>>Salvesta parool</button>
        </form>
    </div>
</div>
</body>
</html>
