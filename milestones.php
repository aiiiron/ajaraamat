<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$familyId = current_family_id();
$children = get_children($familyId);
if (empty($children)) {
    header('Location: children.php');
    exit;
}
$child = resolve_current_child($familyId);
$childId = (int) $child['id'];

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'milestone_new') {
    require_csrf();
    $label = trim($_POST['label'] ?? '');
    $emoji = trim($_POST['emoji'] ?? '');
    $date  = $_POST['achieved_on'] ?? '';
    if ($label === '') {
        $error = 'Sisesta verstaposti nimi.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = 'Vali kuupäev.';
    } else {
        add_custom_milestone($childId, $label, $emoji, $date);
        header('Location: milestones.php?child=' . $childId);
        exit;
    }
}

record_milestones($childId);
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Verstapostid — Ajaraamat</title>
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
<div class="wrap">
    <header class="topbar">
        <h1 class="app-logo"><img src="logo-mark.png" alt="" width="44" height="44">Ajaraamat</h1>
        <div class="header-actions">
            <button type="button" class="icon-btn" onclick="location.reload();" aria-label="Värskenda" title="Värskenda"><?= icon("refresh") ?></button>
            <a href="children.php" class="link-muted">Lapsed</a>
            <a href="logout.php" class="link-muted">Logi välja</a>
        </div>
    </header>

    <nav class="tabs">
        <a href="paren.php?child=<?= $childId ?>" class="tab">Töölaud</a>
        <a href="history.php?child=<?= $childId ?>" class="tab">Kanded</a>
        <a href="books.php?child=<?= $childId ?>" class="tab">Raamatud</a>
        <a href="milestones.php?child=<?= $childId ?>" class="tab active">Verstapostid</a>
    </nav>

    <?php render_child_switcher($children, $childId, 'milestones.php'); ?>

    <?php render_milestone_banner($childId); ?>

    <div class="card">
        <h2>Saavutatud — <?= htmlspecialchars($child['name']) ?></h2>
        <p class="child-link-note" style="text-align:left;margin:4px 0 14px;">Iga saavutus ja kuupäev, mil see saavutati. Vajuta pliiatsile, et muuta.</p>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php render_milestones_list($childId, true); ?>

        <details class="chal-new"<?= $error ? ' open' : '' ?>>
            <summary>+ Lisa verstapost</summary>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="milestone_new">
                <input type="hidden" name="child" value="<?= $childId ?>">
                <div class="ms-new-row">
                    <input type="text" name="emoji" maxlength="4" placeholder="🏅" value="<?= htmlspecialchars($_POST['emoji'] ?? '') ?>" aria-label="Ikoon" class="ms-emoji-input">
                    <input type="text" name="label" placeholder="nt. Luges esimese raamatu ise läbi" value="<?= htmlspecialchars($_POST['label'] ?? '') ?>" required>
                </div>
                <label for="achieved_on">Kuupäev</label>
                <input type="date" id="achieved_on" name="achieved_on" value="<?= htmlspecialchars($_POST['achieved_on'] ?? date('Y-m-d')) ?>" required>
                <button type="submit" class="btn btn-add full-width">Lisa verstapost</button>
            </form>
        </details>
    </div>

    <div class="card">
        <h2>Kõik automaatsed verstapostid</h2>
        <p class="child-link-note" style="text-align:left;margin:4px 0 14px;">Need tekivad ise, kui laps piirini jõuab. Number pealkirja kõrval on praegune seis.</p>
        <?php render_milestone_catalog($childId); ?>
    </div>
</div>
</body>
</html>
