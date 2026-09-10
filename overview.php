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
$gated = !has_feature($familyId, 'family_overview');
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Kõik lapsed — Ajaraamat</title>
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
    <h2 style="margin-bottom:16px;">Kõik lapsed korraga</h2>

    <?php if ($gated): ?>
        <?php render_upgrade_gate('Kõik lapsed korraga') ?>
    <?php else: ?>
    <?php foreach ($children as $c):
        $cid = (int) $c['id'];
        $totals = get_totals($cid);
        $stats = get_stats_matrix($cid);
        $streak = get_current_streak($cid);
        $owed = $totals['owed'];
        if ($owed > 0) {
            $balLabel = format_duration($owed) . ' võlgu';
            $balClass = 'owed';
        } elseif ($owed < 0) {
            $balLabel = format_duration(abs($owed)) . ' boonust';
            $balClass = 'credit';
        } else {
            $balLabel = 'Tasakaalus!';
            $balClass = 'even';
        }
    ?>
    <div class="card">
        <div class="card-header">
            <h2><?= htmlspecialchars($c['name']) ?></h2>
            <?php if ($streak > 0): ?><span class="ov-streak"><?= icon('flame') ?> <?= $streak ?></span><?php endif; ?>
        </div>
        <div class="balance-card <?= $balClass ?>" style="margin-bottom:14px;box-shadow:none;padding:16px;">
            <div class="balance-label">Tasakaal</div>
            <div class="balance-value"><?= htmlspecialchars($balLabel) ?></div>
            <?php render_balance_bar($totals['raamat'], $totals['ekraan']); ?>
        </div>
        <div class="tile-grid" style="margin-bottom:14px;">
            <div class="period-tile">
                <div class="pt-label">Täna</div>
                <div class="pt-row pt-reading"><span class="pt-k"><?= emoji_svg('books') ?></span><span class="pt-t">Raamat</span><span class="pt-v"><?= format_duration($stats['today_raamat']) ?></span></div>
                <div class="pt-row pt-screen"><span class="pt-k"><?= emoji_svg('screen') ?></span><span class="pt-t">Ekraan</span><span class="pt-v"><?= format_duration($stats['today_ekraan']) ?></span></div>
            </div>
            <div class="period-tile">
                <div class="pt-label">See nädal</div>
                <div class="pt-row pt-reading"><span class="pt-k"><?= emoji_svg('books') ?></span><span class="pt-t">Raamat</span><span class="pt-v"><?= format_duration($stats['week_raamat']) ?></span></div>
                <div class="pt-row pt-screen"><span class="pt-k"><?= emoji_svg('screen') ?></span><span class="pt-t">Ekraan</span><span class="pt-v"><?= format_duration($stats['week_ekraan']) ?></span></div>
            </div>
        </div>
        <a href="paren.php?child=<?= $cid ?>" class="btn btn-add full-width">Ava <?= htmlspecialchars($c['name']) ?> →</a>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
