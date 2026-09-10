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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $act = $_POST['action'] ?? '';
    if ($act === 'challenge_new') {
        $title = trim($_POST['title'] ?? '');
        $type  = $_POST['goal_type'] ?? 'books';
        $value = (int) ($_POST['goal_value'] ?? 0);
        $start = $_POST['start_date'] ?? '';
        $end   = $_POST['end_date'] ?? '';
        $validDates = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) && $end >= $start;
        if ($title !== '' && $value > 0 && $validDates) {
            create_challenge($childId, $title, $type, $value, $start, $end);
        }
    } elseif ($act === 'challenge_del') {
        delete_challenge((int) ($_POST['challenge_id'] ?? 0), $childId);
    }
    header('Location: books.php?child=' . $childId);
    exit;
}

$books = get_books($childId);
$challenges = get_challenges($childId);
record_milestones($childId);

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
            <?php if (is_admin()): ?>
                <a href="admin.php" class="link-muted">Admin</a>
            <?php endif; ?>
            <a href="logout.php" class="link-muted">Logi välja</a>
        </div>
    </header>

    <nav class="tabs">
        <a href="paren.php?child=<?= $childId ?>" class="tab">Töölaud</a>
        <a href="history.php?child=<?= $childId ?>" class="tab">Kanded</a>
        <a href="books.php?child=<?= $childId ?>" class="tab active">Raamatud</a>
        <a href="milestones.php?child=<?= $childId ?>" class="tab">Verstapostid</a>
    </nav>

    <?php render_child_switcher($children, $childId, 'books.php'); ?>

    <div class="card">
        <div class="ys-head">
            <h2>Aasta kokkuvõte</h2>
            <?php render_year_nav($year, $minYear, $maxYear, 'books.php?child=' . $childId); ?>
        </div>
        <?php render_year_summary($yearSummary); ?>
    </div>

    <?php render_milestone_banner($childId); ?>

    <div class="card">
        <h2>Väljakutsed</h2>
        <?php if (empty($challenges)): ?>
            <p class="empty">Väljakutseid pole veel.</p>
        <?php else: ?>
            <?php foreach ($challenges as $ch): ?>
                <?php render_challenge_card($ch, get_challenge_progress($ch), true); ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <details class="chal-new">
            <summary>+ Uus väljakutse</summary>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="child" value="<?= $childId ?>">
                <input type="hidden" name="action" value="challenge_new">
                <label for="ch_title">Pealkiri</label>
                <input type="text" id="ch_title" name="title" placeholder="nt. Suvine lugemismaraton" required>
                <label>Eesmärk</label>
                <div class="chal-goal-row">
                    <input type="number" name="goal_value" min="1" placeholder="nt. 10" required>
                    <select name="goal_type">
                        <option value="books">raamatut</option>
                        <option value="minutes">minutit</option>
                    </select>
                </div>
                <div class="chal-goal-row">
                    <div><label for="ch_start">Algus</label><input type="date" id="ch_start" name="start_date" required></div>
                    <div><label for="ch_end">Lõpp</label><input type="date" id="ch_end" name="end_date" required></div>
                </div>
                <button type="submit" class="btn btn-add full-width">Loo väljakutse</button>
            </form>
        </details>
    </div>

    <div class="actions">
        <a href="add_book.php?child=<?= $childId ?>" class="btn btn-add full-width"><?= icon("plus") ?> Lisa raamat</a>
    </div>

    <div class="card">
        <h2>Kõik raamatud</h2>
        <?php render_books_table($books, true); ?>
    </div>
</div>
</body>
</html>
