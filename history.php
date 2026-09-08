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

$q = trim($_GET['q'] ?? '');

if ($q !== '') {
    $entries = search_entries($childId, $q);
    $totalPages = 1;
    $page = 1;
} else {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 10;
    $totalDates = count_distinct_dates($childId);
    $totalPages = max(1, (int) ceil($totalDates / $perPage));
    $page = min($page, $totalPages);
    $entries = get_entries_page($childId, $page, $perPage);
}
$totals = get_totals($childId);
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Kõik kanded — Ajaraamat</title>
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
            <a href="logout.php" class="link-muted">Logi välja</a>
        </div>
    </header>

    <nav class="tabs">
        <a href="paren.php?child=<?= $childId ?>" class="tab">Töölaud</a>
        <a href="history.php?child=<?= $childId ?>" class="tab active">Kõik kanded</a>
        <a href="books.php?child=<?= $childId ?>" class="tab">Raamatud</a>
        <a href="children.php" class="tab">Lapsed</a>
    </nav>

    <?php render_child_switcher($children, $childId, 'history.php'); ?>

    <?php
    $owed = (int) $totals['owed'];
    $balClass = $owed > 0 ? 'bal-owed' : ($owed < 0 ? 'bal-bonus' : 'bal-even');
    $balText  = $owed > 0
        ? 'Võlgu ' . format_duration($owed)
        : ($owed < 0 ? 'Boonuses ' . format_duration(abs($owed)) : 'Tasakaalus');
    ?>
    <div class="period-tile pt-total hist-total">
        <div class="pt-label">Kokku</div>
        <div class="pt-row pt-reading"><span class="pt-k"><?= emoji_svg('books') ?></span><span class="pt-t">Raamat</span><span class="pt-v"><?= format_duration((int) $totals['raamat']) ?></span></div>
        <div class="pt-row pt-screen"><span class="pt-k"><?= emoji_svg('screen') ?></span><span class="pt-t">Ekraan</span><span class="pt-v"><?= format_duration((int) $totals['ekraan']) ?></span></div>
        <div class="pt-row pt-balance <?= $balClass ?>"><span class="pt-k">⚖️</span><span class="pt-t">Tasakaal</span><span class="pt-v"><?= htmlspecialchars($balText) ?></span></div>
    </div>

    <div class="actions">
        <a href="add.php?child=<?= $childId ?>" class="btn btn-add full-width"><?= icon("plus") ?> Lisa kanne</a>
    </div>

    <div class="card">
        <h2><?= htmlspecialchars($child['name']) ?> — kõik kanded</h2>
        <form method="get" class="search-row">
            <input type="hidden" name="child" value="<?= $childId ?>">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Otsi kommentaari järgi (nt. Youtube, Karlsson)">
            <button type="submit" class="btn btn-add"><?= icon("search") ?> Otsi</button>
        </form>
        <?php if ($q !== ''): ?>
            <p class="child-link-note" style="text-align:left;margin:-8px 0 12px;">
                <?= count($entries) ?> tulemust otsingule "<?= htmlspecialchars($q) ?>" —
                <a href="history.php?child=<?= $childId ?>">tühjenda otsing</a>
            </p>
        <?php endif; ?>
        <?php render_pagination($page, $totalPages, 'history.php', ['child' => $childId]); ?>
        <?php render_entries_table($entries, true, $childId); ?>
        <?php render_pagination($page, $totalPages, 'history.php', ['child' => $childId]); ?>
    </div>
</div>
</body>
</html>
