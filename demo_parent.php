<?php
// One-tap login to the shared demo family. No password — the account holds
// only throwaway data and is re-seeded nightly.
//
// Safety: this must never silently overwrite an already-logged-in REAL
// account's session (that's exactly what a shared PHPSESSID would otherwise
// do). If the visitor is currently logged in as themselves, they have to
// explicitly log out first — one extra click below — before switching into
// the demo.
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

$pdo = get_db();
$id = (int) $pdo->query("SELECT id FROM families WHERE is_demo = 1 AND status = 'approved' ORDER BY id ASC LIMIT 1")->fetchColumn();

if ($id > 0) {
    session_regenerate_id(true);
    unset($_SESSION['is_admin']);
    $_SESSION['family_id']   = $id;
    $_SESSION['login_kind']  = 'demo';
    $_SESSION['login_id']    = $id;
    $_SESSION['login_email'] = 'demo';
    $_SESSION['is_demo']     = 1;
    header('Location: paren.php');
    exit;
}

header('Location: login.php');
exit;
