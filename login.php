<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
configure_session();

// Already logged in? Skip straight to the dashboard instead of showing the form again.
if (!empty($_SESSION['family_id'])) {
    header('Location: paren.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $now = time();
    $lockUntil = (int) ($_SESSION['login_lock_until'] ?? 0);

    if ($now < $lockUntil) {
        $error = 'Liiga palju ebaõnnestunud katseid. Proovi ' . ($lockUntil - $now) . ' sekundi pärast uuesti.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT * FROM families WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $family = $stmt->fetch();

        if (!$family || !password_verify($password, $family['password_hash'])) {
            $_SESSION['login_fails'] = (int) ($_SESSION['login_fails'] ?? 0) + 1;
            if ($_SESSION['login_fails'] >= 5) {
                $_SESSION['login_lock_until'] = $now + 60;
                $_SESSION['login_fails'] = 0;
            }
            $error = 'Vale e-post või parool.';
        } elseif ($family['status'] === 'pending') {
            $error = 'Sinu konto ootab veel kinnitust. Anname e-postiga teada, kui see on kinnitatud.';
        } elseif ($family['status'] === 'rejected') {
            $error = 'Sinu kontoga on probleem. Võta ühendust saidi omanikuga.';
        } else {
            unset($_SESSION['login_fails'], $_SESSION['login_lock_until']);
            $_SESSION['family_id'] = $family['id'];
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Logi sisse — Ajaraamat</title>
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" href="icon-192.png" type="image/png">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
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
    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post">
        <label for="email">E-post</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autofocus required>

        <label for="password">Parool</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Logi sisse</button>
    </form>
    <a href="register.php" class="link-muted login-home-link">Pole veel kontot? Registreeru</a><br>
    <a href="index.php" class="link-muted login-home-link">← Avaleht</a>
</div>
</body>
</html>
