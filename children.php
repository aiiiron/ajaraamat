<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$familyId = current_family_id();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (isset($_POST['delete_id'])) {
        $childId = (int) $_POST['delete_id'];
        if (child_belongs_to_family($childId, $familyId)) {
            delete_child($childId);
        }
        header('Location: children.php');
        exit;
    }
    if (($_POST['action'] ?? '') === 'goal') {
        $cid = (int) ($_POST['child_id'] ?? 0);
        if (child_belongs_to_family($cid, $familyId)) {
            $d = (int) ($_POST['daily_goal_min'] ?? 0);
            $w = (int) ($_POST['weekly_goal_min'] ?? 0);
            set_child_goal($cid, $d > 0 ? $d : null, $w > 0 ? $w : null);
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
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Lapsed — Ajaraamat</title>
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
<body>
<div class="wrap">
    <header class="topbar">
        <h1 class="app-logo"><img src="logo-mark.png" alt="" width="44" height="44">Ajaraamat</h1>
        <div class="header-actions">
            <button type="button" class="icon-btn" onclick="location.reload();" aria-label="Värskenda" title="Värskenda"><?= icon("refresh") ?></button>
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
                <?= csrf_field() ?>
                <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn-delete-full">Kustuta laps</button>
            </form>
        </div>

        <form method="post" class="goal-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="goal">
            <input type="hidden" name="child_id" value="<?= $c['id'] ?>">
            <p class="goal-form-head">Lugemiseesmärk</p>
            <div class="goal-form-row">
                <div>
                    <label for="dg<?= $c['id'] ?>">Päevas (min)</label>
                    <input type="number" id="dg<?= $c['id'] ?>" name="daily_goal_min" min="0" inputmode="numeric" placeholder="nt. 30" value="<?= (int) ($c['daily_goal_min'] ?? 0) ?: '' ?>">
                </div>
                <div>
                    <label for="wg<?= $c['id'] ?>">Nädalas (min)</label>
                    <input type="number" id="wg<?= $c['id'] ?>" name="weekly_goal_min" min="0" inputmode="numeric" placeholder="nt. 210" value="<?= (int) ($c['weekly_goal_min'] ?? 0) ?: '' ?>">
                </div>
                <button type="submit" class="btn btn-add">Salvesta</button>
            </div>
        </form>
    </div>
    <?php endforeach; ?>

    <div class="card">
        <h2>Lisa uus laps</h2>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label for="name">Nimi</label>
            <input type="text" id="name" name="name" placeholder="nt. Mari" required>
            <button type="submit" class="btn btn-add full-width">Lisa laps</button>
        </form>
    </div>
</div>
</body>
</html>
