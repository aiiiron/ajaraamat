<?php
require_once __DIR__ . '/functions.php';

$token = $_GET['token'] ?? '';
$child = $token !== '' ? get_child_by_token($token) : null;

if (!$child) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="et"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ei leitud — Ajaraamat</title><link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css"></head>
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
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Raamatud — Ajaraamat</title>
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" href="icon-192.png" type="image/png">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#8B5CF6">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrap">
    <header class="topbar">
        <h1 class="app-logo"><img src="logo-mark.png" alt="" width="44" height="44">Ajaraamat</h1>
        <div class="header-actions">
            <button type="button" class="icon-btn" onclick="location.reload();" aria-label="Värskenda" title="Värskenda">⟳</button>
        </div>
    </header>

    <nav class="tabs">
        <a href="child.php?token=<?= htmlspecialchars($token) ?>" class="tab">Kokkuvõte</a>
        <a href="child_books.php?token=<?= htmlspecialchars($token) ?>" class="tab active">Raamatud</a>
    </nav>

    <div class="stat-card" style="margin-bottom:16px;">
        <div class="stat-label">Loetud raamatuid kokku</div>
        <div class="stat-value"><?= $finishedCount ?></div>
    </div>

    <?php if ($milestone): ?>
        <div class="milestone-banner">🎉 Verstapost saavutatud: <?= $milestone ?> raamatut loetud!</div>
    <?php endif; ?>

    <div class="card">
        <h2>Kõik raamatud</h2>
        <?php render_books_table($books, false); ?>
    </div>
</div>
</body>
</html>
