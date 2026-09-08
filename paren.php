<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$familyId = current_family_id();
$children = get_children($familyId);

if (empty($children)) {
    header('Location: children.php');
    exit;
}

$child = resolve_current_child($familyId);
$childId = (int) $child['id'];

$totals = get_totals($childId);
$stats = get_stats_matrix($childId);
$streak = get_current_streak($childId);
$daily = get_daily_totals_range($childId, 30);
$topBooks = get_top_books($childId, 5);
$topScreen = get_top_comments($childId, 'ekraan', 5);

$recent = get_entries_page($childId, 1, 5); // dashboard shows only the latest 5

$owed = $totals['owed'];
if ($owed > 0) {
    $balanceLabel = "Võlgu $owed min lugemist";
    $balanceClass = "owed";
} elseif ($owed < 0) {
    $balanceLabel = abs($owed) . " min lugemise boonust";
    $balanceClass = "credit";
} else {
    $balanceLabel = "Tasakaalus!";
    $balanceClass = "even";
}

$chartLabels = array_map(fn($d) => date('d.m', strtotime($d['date'])), $daily);
$chartRaamat = array_map(fn($d) => $d['raamat'], $daily);
$chartEkraan = array_map(fn($d) => $d['ekraan'], $daily);
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
<div class="wrap">
    <header class="topbar">
        <h1 class="app-logo"><img src="logo-mark.png" alt="" width="44" height="44">Ajaraamat</h1>
        <div class="header-actions">
            <button type="button" class="icon-btn" onclick="location.reload();" aria-label="Värskenda" title="Värskenda"><?= icon("refresh") ?></button>
            <?php if (is_admin()): ?>
                <a href="admin.php" class="link-muted">Admin</a>
            <?php endif; ?>
            <a href="logout.php" class="link-muted">Logi välja</a>
        </div>
    </header>

    <nav class="tabs">
        <a href="paren.php?child=<?= $childId ?>" class="tab active">Töölaud</a>
        <a href="history.php?child=<?= $childId ?>" class="tab">Kõik kanded</a>
        <a href="books.php?child=<?= $childId ?>" class="tab">Raamatud</a>
        <a href="children.php" class="tab">Lapsed</a>
    </nav>

    <?php render_child_switcher($children, $childId, 'paren.php'); ?>

    <?php if (($_GET['saved'] ?? '') === '1'): ?>
        <div class="milestone-banner"><?= icon("check") ?> Kanne salvestatud!</div>
    <?php endif; ?>

    <?php if ((int) date('N') <= 2): ?>
        <a class="wk-banner" href="week.php?child=<?= $childId ?>">📊 Eelmise nädala kokkuvõte →</a>
    <?php endif; ?>

    <?php if ($streak > 0): ?>
        <div class="streak-badge"><?= icon("flame") ?> <?= $streak ?> päeva järjest tasakaalus</div>
    <?php endif; ?>

    <div class="balance-card <?= $balanceClass ?>">
        <div class="balance-label"><?= htmlspecialchars($child['name']) ?> tasakaal</div>
        <div class="balance-value"><?= htmlspecialchars($balanceLabel) ?></div>
        <?php render_balance_bar($totals['raamat'], $totals['ekraan']); ?>
    </div>

    <?php render_reward_card((int) $owed, (int) ($child['screen_reward_cap_min'] ?? 0)); ?>

    <?php render_goal_card((int) $stats['today_raamat'], (int) $stats['week_raamat'], (int) ($child['daily_goal_min'] ?? 0), (int) ($child['weekly_goal_min'] ?? 0)); ?>

    <?php
    $todayDate = date('Y-m-d');
    $activeChallenges = array_filter(get_challenges($childId), fn($ch) => $todayDate >= $ch['start_date'] && $todayDate <= $ch['end_date']);
    ?>
    <?php if ($activeChallenges): ?>
        <div class="card">
            <div class="card-header">
                <h2>Väljakutse<?= count($activeChallenges) > 1 ? 'd' : '' ?></h2>
                <a href="books.php?child=<?= $childId ?>" class="link-muted">Halda →</a>
            </div>
            <?php foreach ($activeChallenges as $ch): ?>
                <?php render_challenge_card($ch, get_challenge_progress($ch), false); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php render_stats_tiles($stats); ?>
    <?php if ($stats['today_raamat'] === 0 && $stats['today_ekraan'] === 0): ?>
        <p class="child-link-note" style="margin-top:-8px;margin-bottom:16px;">Täna pole veel midagi lisatud.</p>
    <?php endif; ?>

    <div class="actions">
        <a href="add.php?child=<?= $childId ?>" class="btn btn-add full-width"><?= icon("plus") ?> Lisa kanne</a>
    </div>

    <section class="card">
        <h2>Viimased 30 päeva</h2>
        <canvas id="trendChart" height="160"></canvas>
    </section>

    <section class="card">
        <h2>Kuu ülevaade</h2>
        <?php render_heatmap($daily); ?>
    </section>

    <?php if (!empty($topBooks) || !empty($topScreen)): ?>
    <section class="card">
        <h2>Enim aega</h2>
        <?php if (!empty($topBooks)): ?>
            <div class="top-block tb-reading">
                <p class="top-heading">📖 Raamatud <span class="bl-count"><?= count($topBooks) ?></span></p>
                <ul class="top-list">
                    <?php foreach ($topBooks as $t): ?>
                        <li><span class="top-name"><?= htmlspecialchars($t['title']) ?></span><span class="top-minutes"><?= format_duration((int) $t['minutes']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($topScreen)): ?>
            <div class="top-block tb-screen">
                <p class="top-heading">📱 Ekraan <span class="bl-count"><?= count($topScreen) ?></span></p>
                <ul class="top-list">
                    <?php foreach ($topScreen as $t): ?>
                        <li><span class="top-name"><?= htmlspecialchars($t['comment']) ?></span><span class="top-minutes"><?= format_duration((int) $t['minutes']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="card">
        <div class="card-header">
            <h2>Viimased kanded</h2>
            <a href="history.php?child=<?= $childId ?>" class="link-muted">Kõik kanded →</a>
        </div>
        <?php render_entries_table($recent, false); ?>
    </section>

    <p class="child-link-note">
        <a href="week.php?child=<?= $childId ?>">Eelmine nädal →</a>
        &nbsp;·&nbsp;
        <a href="children.php"><?= htmlspecialchars($child['name']) ?> enda link →</a>
        &nbsp;·&nbsp;
        <a href="export.php?child=<?= $childId ?>">Laadi CSV alla</a>
    </p>
</div>
<script>
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
            {
                label: 'Raamat',
                data: <?= json_encode($chartRaamat) ?>,
                borderColor: '#F0578F',
                backgroundColor: 'rgba(240,87,143,0.16)',
                tension: 0.3,
                fill: true
            },
            {
                label: 'Ekraan',
                data: <?= json_encode($chartEkraan) ?>,
                borderColor: '#5B7FE8',
                backgroundColor: 'rgba(91,127,232,0.16)',
                tension: 0.3,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, title: { display: true, text: 'min' } } }
    }
});
</script>
</body>
</html>
