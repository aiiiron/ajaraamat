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

$wk = max(0, (int) ($_GET['wk'] ?? 1));
[$start, $end]   = get_week_bounds($wk);
[$pStart, $pEnd] = get_week_bounds($wk + 1);

$recap = get_week_recap($childId, $start, $end);
$prev  = get_week_recap($childId, $pStart, $pEnd);

$dailyGoal  = (int) ($child['daily_goal_min'] ?? 0);
$weeklyGoal = (int) ($child['weekly_goal_min'] ?? 0);
$daysGoalMet = 0;
if ($dailyGoal > 0) {
    foreach ($recap['per_day'] as $m) {
        if ($m >= $dailyGoal) {
            $daysGoalMet++;
        }
    }
}

$weekChallenges = array_filter(
    get_challenges($childId),
    fn($ch) => $ch['start_date'] <= $end && $ch['end_date'] >= $start
);

$label = $wk === 1 ? 'Eelmine nädal' : ($wk === 0 ? 'See nädal' : $wk . ' nädalat tagasi');
$hasReading = $recap['raamat'] > 0 || $recap['ekraan'] > 0;
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Nädala kokkuvõte — Ajaraamat</title>
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

    <header class="topbar" style="margin-bottom:16px;">
        <a href="paren.php?child=<?= $childId ?>" class="link-muted"><?= icon("arrow-left") ?> Töölaud</a>
    </header>

    <?php render_child_switcher($children, $childId, 'week.php'); ?>

    <div class="card">
        <div class="ys-head">
            <h2><?= $label ?></h2>
            <div class="ys-year">
                <a href="week.php?child=<?= $childId ?>&wk=<?= $wk + 1 ?>" aria-label="Varasem">‹</a>
                <span><?= date('d.m', strtotime($start)) ?>–<?= date('d.m', strtotime($end)) ?></span>
                <?php if ($wk > 0): ?>
                    <a href="week.php?child=<?= $childId ?>&wk=<?= $wk - 1 ?>" aria-label="Hilisem">›</a>
                <?php else: ?><span class="disabled">›</span><?php endif; ?>
            </div>
        </div>

        <div class="wk-grid">
            <div class="wk-stat">
                <div class="wk-l">📖 Loetud</div>
                <div class="wk-n"><?= format_duration($recap['raamat']) ?></div>
                <?= render_wk_delta($recap['raamat'], $prev['raamat']) ?>
            </div>
            <div class="wk-stat">
                <div class="wk-l">📱 Ekraan</div>
                <div class="wk-n"><?= format_duration($recap['ekraan']) ?></div>
                <?= render_wk_delta($recap['ekraan'], $prev['ekraan'], true) ?>
            </div>
        </div>
        <p class="wk-sub">
            <?= $recap['reading_days'] ?> / 7 lugemispäeva<?php if ($prev['reading_days'] > 0): ?> · eelmisel nädalal <?= $prev['reading_days'] ?><?php endif; ?>
        </p>
    </div>

    <div class="card">
        <h2>Tipphetked</h2>
        <ul class="wk-list">
            <?php if ($recap['best_day']): ?>
                <li><span>Parim päev</span><span><?= et_weekday($recap['best_day']['date']) ?> · <?= format_duration($recap['best_day']['min']) ?></span></li>
            <?php endif; ?>
            <?php if ($recap['top_book']): ?>
                <li><span>Enim loetud</span><span><?= htmlspecialchars($recap['top_book']['title']) ?> (<?= format_duration($recap['top_book']['min']) ?>)</span></li>
            <?php endif; ?>
            <?php if ($weeklyGoal > 0): ?>
                <li><span>Nädala eesmärk</span><span><?= format_duration($recap['raamat']) ?> / <?= format_duration($weeklyGoal) ?><?= $recap['raamat'] >= $weeklyGoal ? ' ✅' : '' ?></span></li>
            <?php endif; ?>
            <?php if ($dailyGoal > 0): ?>
                <li><span>Päevaeesmärk täidetud</span><span><?= $daysGoalMet ?> / 7 päeval</span></li>
            <?php endif; ?>
            <?php if (!$hasReading && $weeklyGoal <= 0 && $dailyGoal <= 0): ?>
                <li><span>Sel nädalal kandeid ei olnud.</span></li>
            <?php endif; ?>
        </ul>
    </div>

    <?php if ($weekChallenges): ?>
    <div class="card">
        <h2>Väljakutsed</h2>
        <?php foreach ($weekChallenges as $ch): ?>
            <?php render_challenge_card($ch, get_challenge_progress($ch), false); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
