<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $childName = trim($_POST['child_name'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Sisesta kehtiv e-posti aadress.';
    } elseif (strlen($password) < 6) {
        $error = 'Parool peab olema vähemalt 6 tähemärki.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Paroolid ei kattu.';
    } elseif ($childName === '') {
        $error = 'Sisesta lapse nimi.';
    } else {
        $pdo = get_db();
        if (parent_email_exists($email)) {
            $error = 'Selle e-postiga konto on juba olemas.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO families (email, password_hash, status) VALUES (:email, :hash, 'pending')");
            $stmt->execute([
                ':email' => $email,
                ':hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $familyId = (int) $pdo->lastInsertId();
            create_child($familyId, $childName);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Loo konto · Ajaraamat</title>
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

    <?php if ($success): ?>
        <p style="margin:16px 0;">Aitäh registreerimast! Sinu konto ootab hetkel kinnitust. Anname e-postiga teada, kui saad sisse logida.</p>
        <a href="index.php" class="link-muted login-home-link">← Avaleht</a>
    <?php else: ?>
        <p style="color:var(--text-muted);font-size:14px;margin-bottom:16px;">Loo oma pere konto</p>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="post">
            <label for="email">E-post</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>

            <label for="password">Parool</label>
            <input type="password" id="password" name="password" required minlength="6">

            <label for="password_confirm">Parool uuesti</label>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="6">

            <label for="child_name">Lapse nimi</label>
            <input type="text" id="child_name" name="child_name" value="<?= htmlspecialchars($_POST['child_name'] ?? '') ?>" placeholder="nt. Mari" required>

            <button type="submit" class="btn btn-add full-width">Loo konto</button>
        </form>
        <a href="login.php" class="link-muted login-home-link">Konto juba olemas? Logi sisse</a>
    <?php endif; ?>
</div>
</body>
</html>
