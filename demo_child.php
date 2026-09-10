<?php
// Demo login for the child side — NOT a public bypass. Real children never
// have a password (their token link is the credential); this form exists only
// to gate the shared demo child behind DEMO_CHILD_EMAIL / DEMO_CHILD_PASSWORD
// (configured in config.php, never committed to git) before redirecting to
// its normal, otherwise-unguessable child.php?token= link.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $demoEmail = defined('DEMO_CHILD_EMAIL') ? (string) DEMO_CHILD_EMAIL : '';
    $demoPass  = defined('DEMO_CHILD_PASSWORD') ? (string) DEMO_CHILD_PASSWORD : '';

    if ($demoEmail === '' || $demoPass === '' || $email !== $demoEmail || !hash_equals($demoPass, $password)) {
        $error = 'Vale e-post või parool.';
    } else {
        $pdo = get_db();
        $token = (string) $pdo->query("SELECT c.public_token
            FROM children c JOIN families f ON f.id = c.family_id
            WHERE f.is_demo = 1 AND f.status = 'approved'
            ORDER BY c.id ASC LIMIT 1")->fetchColumn();
        if ($token !== '') {
            header('Location: child.php?token=' . urlencode($token));
            exit;
        }
        $error = 'Demo pole hetkel saadaval.';
    }
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Proovi demot · Ajaraamat</title>
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
    <h2>Demo: lapse vaade</h2>
    <p style="margin:4px 0 14px;color:var(--text-muted);font-size:14px;">Küsi demo sisselogimisandmed saidi omanikult.</p>
    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post">
        <label for="email">E-post</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autofocus required>

        <label for="password">Parool</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" class="btn btn-add full-width">Ava</button>
    </form>
    <a href="index.php" class="link-muted login-home-link">← Avaleht</a>
</div>
</body>
</html>
