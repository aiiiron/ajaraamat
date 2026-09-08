<?php
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
$books = get_books($childId);
$finishedCount = count_finished_books($childId);
$milestone = get_book_milestone($finishedCount);

[$minYear, $maxYear] = get_reading_year_range($childId);
$year = (int) ($_GET['year'] ?? date('Y'));
$year = max($minYear, min($maxYear, $year));
$yearSummary = get_year_summary($childId, $year);
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Raamatud — Ajaraamat</title>
<link rel="icon" href="favicon.ico?v=2" sizes="any">
<link rel="icon" href="icon-192.png?v=2" type="image/png">
<link rel="apple-touch-icon" href="apple-touch-icon.png?v=2">
<link rel="manifest" href="manifest.php?token=<?= urlencode($token) ?>">
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
        </div>
    </header>

    <nav class="tabs">
        <a href="child.php?token=<?= htmlspecialchars($token) ?>" class="tab">Kokkuvõte</a>
        <a href="child_books.php?token=<?= htmlspecialchars($token) ?>" class="tab active">Raamatud</a>
    </nav>

    <div class="card">
        <div class="ys-head">
            <h2>Aasta kokkuvõte</h2>
            <?php render_year_nav($year, $minYear, $maxYear, 'child_books.php?token=' . urlencode($token)); ?>
        </div>
        <?php render_year_summary($yearSummary); ?>
    </div>

    <?php if ($milestone): ?>
        <div class="milestone-banner">🎉 Verstapost saavutatud: <?= $milestone ?> raamatut loetud!</div>
    <?php endif; ?>

    <?php
    $today = date('Y-m-d');
    $challenges = array_filter(get_challenges($childId), fn($ch) => $today <= $ch['end_date']);
    ?>
    <?php if ($challenges): ?>
    <div class="card">
        <h2>Väljakutsed</h2>
        <?php foreach ($challenges as $ch): ?>
            <?php render_challenge_card($ch, get_challenge_progress($ch), false); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>Kõik raamatud</h2>
        <?php render_books_table($books, false); ?>
    </div>
</div>
</body>
</html>
