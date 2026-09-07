<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$familyId = current_family_id();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $childId = (int) $_POST['delete_id'];
        if (child_belongs_to_family($childId, $familyId)) {
            delete_child($childId);
        }
        header('Location: children.php');
        exit;
    }
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $error = 'Sisesta lapse nimi.';
    } else {
        create_child($familyId, $name);
        header('Location: children.php');
        exit;
    }
}

$children = get_children($familyId);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$baseUrl = $scheme . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lapsed — Ajaraamat</title>
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" href="icon-192.png" type="image/png">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#8B5CF6">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrap">
    <header class="topbar">
        <h1 class="app-logo"><img src="logo-mark.png" alt="" width="44" height="44">Ajaraamat</h1>
        <div class="header-actions">
            <button type="button" class="icon-btn" onclick="location.reload();" aria-label="Värskenda" title="Värskenda">⟳</button>
            <a href="logout.php" class="link-muted">Logi välja</a>
        </div>
    </header>

    <nav class="tabs">
        <a href="paren.php" class="tab">Töölaud</a>
        <a href="history.php" class="tab">Kõik kanded</a>
        <a href="books.php" class="tab">Raamatud</a>
        <a href="children.php" class="tab active">Lapsed</a>
    </nav>

    <?php foreach ($children as $c):
        $link = $baseUrl . '/child.php?token=' . $c['public_token'];
    ?>
    <div class="card">
        <div class="card-header">
            <h2><?= htmlspecialchars($c['name']) ?></h2>
        </div>
        <p style="font-size:14px;color:var(--text-muted);margin-bottom:8px;">
            Lapse enda link (ilma sisselogimiseta) — jaga seda lapse enda seadmesse järjehoidjaks:
        </p>
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:14px;">
            <input type="text" readonly value="<?= htmlspecialchars($link) ?>" onclick="this.select();" style="flex:1;font-size:13px;">
        </div>
        <div style="display:flex;gap:10px;">
            <a href="paren.php?child=<?= $c['id'] ?>" class="btn btn-add" style="flex:1;text-align:center;">Vaata andmeid</a>
            <form method="post" onsubmit="return confirm('Kustutada <?= htmlspecialchars(addslashes($c['name'])) ?> ja kõik tema kanded/raamatud jäädavalt?');">
                <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn-delete-full">Kustuta laps</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="card">
        <h2>Lisa uus laps</h2>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="post">
            <label for="name">Nimi</label>
            <input type="text" id="name" name="name" placeholder="nt. Mari" required>
            <button type="submit" class="btn btn-add full-width">Lisa laps</button>
        </form>
    </div>
</div>
</body>
</html>
