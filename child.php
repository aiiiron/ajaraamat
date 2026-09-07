<?php
// Public, no password — resolved entirely by the unguessable token in the URL.
require_once __DIR__ . '/functions.php';

$token = $_GET['token'] ?? '';
$child = $token !== '' ? get_child_by_token($token) : null;

if (!$child) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="et"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ei leitud — Ajaraamat</title><link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>"></head>
    <body><div class="wrap narrow"><div class="card" style="text-align:center;margin-top:60px;">
    <h2>Seda linki ei leitud</h2>
    <p style="margin-top:8px;color:var(--text-muted);">Palu vanemal link uuesti jagada.</p>
    </div></div></body></html>
    <?php
    exit;
}

$childId = (int) $child['id'];
$totals = get_totals($childId);
$stats = get_stats_matrix($childId);
$streak = get_current_streak($childId);

$hour = (int) date('G');
if ($hour < 12) {
    $greeting = 'Tere hommikust';
} elseif ($hour < 18) {
    $greeting = 'Tere päevast';
} else {
    $greeting = 'Tere õhtust';
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 5;
$totalDates = count_distinct_dates($childId);
$totalPages = max(1, (int) ceil($totalDates / $perPage));
$page = min($page, $totalPages);
$recent = get_entries_page($childId, $page, $perPage);

$owed = $totals['owed'];
if ($owed > 0) {
    $balanceLabel = "Sa oled $owed min lugemist võlgu";
    $balanceClass = "owed";
} elseif ($owed < 0) {
    $balanceLabel = abs($owed) . " min lugemise boonust!";
    $balanceClass = "credit";
} else {
    $balanceLabel = "Tasakaalus!";
    $balanceClass = "even";
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Ajaraamat</title>
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" href="icon-192.png" type="image/png">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<link rel="manifest" href="manifest.php?token=<?= urlencode($token) ?>">
<meta name="theme-color" content="#8B5CF6">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Ajaraamat">
<link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>">
</head>
<body>
<div class="wrap">
    <header class="topbar">
        <h1 class="app-logo"><img src="logo-mark.png" alt="" width="44" height="44">Ajaraamat</h1>
        <div class="header-actions">
            <button type="button" class="icon-btn" onclick="location.reload();" aria-label="Värskenda" title="Värskenda"><?= icon("refresh") ?></button>
        </div>
    </header>

    <p class="greeting"><?= $greeting ?>, <?= htmlspecialchars($child['name']) ?>! 👋</p>

    <nav class="tabs">
        <a href="child.php?token=<?= htmlspecialchars($token) ?>" class="tab active">Kokkuvõte</a>
        <a href="child_books.php?token=<?= htmlspecialchars($token) ?>" class="tab">Raamatud</a>
    </nav>

    <?php if (($_GET['saved'] ?? '') === '1'): ?>
        <div class="milestone-banner"><?= icon("check") ?> Lugemine salvestatud!</div>
    <?php endif; ?>

    <?php if ($streak > 0): ?>
        <div class="streak-badge"><?= icon("flame") ?> <?= $streak ?> päeva järjest tasakaalus</div>
    <?php endif; ?>

    <div class="balance-card <?= $balanceClass ?>">
        <div class="balance-label">Sinu tasakaal</div>
        <div class="balance-value"><?= htmlspecialchars($balanceLabel) ?></div>
        <?php render_balance_bar($totals['raamat'], $totals['ekraan']); ?>
    </div>

    <?php render_stats_tiles($stats); ?>
    <?php if ($stats['today_raamat'] === 0 && $stats['today_ekraan'] === 0): ?>
        <p class="child-link-note" style="margin-top:-8px;margin-bottom:16px;">Täna pole veel midagi lisatud.</p>
    <?php endif; ?>

    <div class="actions">
        <a href="reading_timer.php?token=<?= htmlspecialchars($token) ?>" class="btn btn-add full-width"><?= icon("timer") ?> Alusta lugemist</a>
    </div>

    <section class="card">
        <div class="card-header">
            <h2>Viimased kanded</h2>
        </div>
        <?php render_entries_table($recent, false); ?>
        <?php render_pagination($page, $totalPages, 'child.php', ['token' => $token]); ?>
    </section>
</div>
</body>
</html>
