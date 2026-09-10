<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$familyId = current_family_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'restore') {
        restore_deleted_entry($id, $familyId);
    } elseif ($action === 'purge') {
        purge_deleted_entry($id, $familyId);
    }
    header('Location: trash.php');
    exit;
}

$rows = get_deleted_entries_for_family($familyId);
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Kustutatud kanded — Ajaraamat</title>
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
    <div class="card">
        <h2>Kustutatud kanded</h2>
        <p class="child-link-note" style="text-align:left;margin:4px 0 14px;">Kustutatud kanded jäävad siia ootama, kuni taastad need või kustutad jäädavalt.</p>
        <?php if (empty($rows)): ?>
            <p class="empty">Kustutatud kandeid pole.</p>
        <?php else: ?>
        <div class="pending-list">
            <?php foreach ($rows as $r):
                $isRaamat = (int) $r['raamat'] > 0;
                $minutes = $isRaamat ? (int) $r['raamat'] : (int) $r['ekraan'];
                $note = $isRaamat ? $r['raamat_comment'] : $r['ekraan_comment'];
                $label = $r['book_title'] ?: ($note ?: ($isRaamat ? 'Raamat' : 'Ekraan'));
            ?>
            <div class="pending-row">
                <div class="pending-main">
                    <span class="tag <?= $isRaamat ? 'tag-reading' : 'tag-screen' ?>"><?= emoji_svg($isRaamat ? 'books' : 'screen') ?> <?= $minutes ?> min</span>
                    <div class="pending-label">
                        <?= htmlspecialchars($label) ?>
                        <span class="pending-meta"><?= htmlspecialchars(date('d.m.Y', strtotime($r['entry_date']))) ?> · <?= htmlspecialchars($r['child_name']) ?> · kustutatud <?= htmlspecialchars(date('d.m', strtotime($r['deleted_at']))) ?></span>
                    </div>
                </div>
                <div class="pending-actions">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="restore">
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button type="submit" class="btn-approve"><?= icon('refresh') ?> Taasta</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Kustutada see kanne jäädavalt? Seda ei saa enam taastada.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="purge">
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button type="submit" class="btn-reject">Kustuta jäädavalt</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
