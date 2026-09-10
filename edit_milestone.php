<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$familyId = current_family_id();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$pdo = get_db();
$stmt = $pdo->prepare("SELECT m.*, c.family_id, c.name AS child_name
    FROM milestones m JOIN children c ON c.id = m.child_id WHERE m.id = :id");
$stmt->execute([':id' => $id]);
$m = $stmt->fetch();
if (!$m || (int) $m['family_id'] !== $familyId) {
    header('Location: milestones.php');
    exit;
}
$childId = (int) $m['child_id'];
$isCustom = milestone_is_custom($m);
$error = '';

require_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if ($isCustom) {
        delete_milestone($id, $childId);
    }
    header('Location: milestones.php?child=' . $childId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $label = trim($_POST['label'] ?? '');
    $emoji = trim($_POST['emoji'] ?? '');
    $date  = $_POST['achieved_on'] ?? '';
    if ($isCustom && $label === '') {
        $error = 'Sisesta saavutuse nimi.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = 'Vali kuupäev.';
    } else {
        // Custom rows store the label; auto / challenge rows keep their text.
        update_milestone($id, $childId, $isCustom ? $label : null, $emoji, $date);
        header('Location: milestones.php?child=' . $childId);
        exit;
    }
}

[$curIcon, $curText] = milestone_text($m);
$label = $_POST['label'] ?? ($m['label'] ?? '');
$emoji = $_POST['emoji'] ?? ($m['emoji'] ?? '');
$date  = $_POST['achieved_on'] ?? $m['achieved_on'];
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Muuda saavutust — Ajaraamat</title>
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
        <a href="milestones.php?child=<?= $childId ?>" class="link-muted"><?= icon("arrow-left") ?> Tagasi</a>
    </header>
    <div class="card">
        <h2>Muuda saavutust — <?= htmlspecialchars($m['child_name']) ?></h2>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <?php if (!$isCustom): ?>
            <p class="child-link-note" style="text-align:left;margin:0 0 12px;">
                Automaatne saavutus — <strong><?= htmlspecialchars($curText) ?></strong>. Muuta saab ikooni ja kuupäeva; teksti arvutab rakendus ise.
            </p>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <input type="hidden" name="child" value="<?= $childId ?>">

            <label for="emoji">Ikoon (valikuline)</label>
            <input type="text" id="emoji" name="emoji" maxlength="4" placeholder="<?= htmlspecialchars($curIcon) ?>" value="<?= htmlspecialchars($emoji) ?>">

            <?php if ($isCustom): ?>
                <label for="label">Nimi</label>
                <input type="text" id="label" name="label" value="<?= htmlspecialchars($label) ?>" required placeholder="nt. Luges esimese raamatu ise läbi">
            <?php endif; ?>

            <label for="achieved_on">Kuupäev</label>
            <input type="date" id="achieved_on" name="achieved_on" value="<?= htmlspecialchars($date) ?>" required>

            <button type="submit" class="btn btn-add full-width">Salvesta muudatused</button>
        </form>

        <?php if ($isCustom): ?>
        <form method="post" onsubmit="return confirm('Kustutada see saavutus?');" class="day-entry-delete-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <input type="hidden" name="child" value="<?= $childId ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn-delete-full">Kustuta see saavutus</button>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
