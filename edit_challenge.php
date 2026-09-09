<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$familyId = current_family_id();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$pdo = get_db();
$stmt = $pdo->prepare("SELECT ch.*, c.family_id, c.name AS child_name
    FROM challenges ch JOIN children c ON c.id = ch.child_id WHERE ch.id = :id");
$stmt->execute([':id' => $id]);
$ch = $stmt->fetch();
if (!$ch || (int) $ch['family_id'] !== $familyId) {
    header('Location: books.php');
    exit;
}
$childId = (int) $ch['child_id'];
$error = '';

require_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    delete_challenge($id, $childId);
    header('Location: books.php?child=' . $childId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $type  = $_POST['goal_type'] ?? 'books';
    $value = (int) ($_POST['goal_value'] ?? 0);
    $start = $_POST['start_date'] ?? '';
    $end   = $_POST['end_date'] ?? '';
    $validDates = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) && $end >= $start;
    if ($title === '') {
        $error = 'Sisesta pealkiri.';
    } elseif ($value <= 0) {
        $error = 'Eesmärk peab olema suurem kui 0.';
    } elseif (!$validDates) {
        $error = 'Kontrolli kuupäevi — lõpp ei tohi olla enne algust.';
    } else {
        update_challenge($id, $childId, $title, $type, $value, $start, $end);
        header('Location: books.php?child=' . $childId);
        exit;
    }
}

$title = $_POST['title'] ?? $ch['title'];
$type  = $_POST['goal_type'] ?? $ch['goal_type'];
$value = $_POST['goal_value'] ?? $ch['goal_value'];
$start = $_POST['start_date'] ?? $ch['start_date'];
$end   = $_POST['end_date'] ?? $ch['end_date'];
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Muuda väljakutset — Ajaraamat</title>
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
        <a href="books.php?child=<?= $childId ?>" class="link-muted"><?= icon("arrow-left") ?> Tagasi</a>
    </header>
    <div class="card">
        <h2>Muuda väljakutset — <?= htmlspecialchars($ch['child_name']) ?></h2>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $ch['id'] ?>">
            <input type="hidden" name="child" value="<?= $childId ?>">

            <label for="ch_title">Pealkiri</label>
            <input type="text" id="ch_title" name="title" value="<?= htmlspecialchars($title) ?>" required>

            <label>Eesmärk</label>
            <div class="chal-goal-row">
                <input type="number" name="goal_value" min="1" value="<?= htmlspecialchars((string) $value) ?>" required>
                <select name="goal_type">
                    <option value="books" <?= $type === 'books' ? 'selected' : '' ?>>raamatut</option>
                    <option value="minutes" <?= $type === 'minutes' ? 'selected' : '' ?>>minutit</option>
                </select>
            </div>

            <div class="chal-goal-row">
                <div><label for="ch_start">Algus</label><input type="date" id="ch_start" name="start_date" value="<?= htmlspecialchars($start) ?>" required></div>
                <div><label for="ch_end">Lõpp</label><input type="date" id="ch_end" name="end_date" value="<?= htmlspecialchars($end) ?>" required></div>
            </div>

            <button type="submit" class="btn btn-add full-width">Salvesta muudatused</button>
        </form>
        <form method="post" onsubmit="return confirm('Kustutada see väljakutse?');" class="day-entry-delete-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $ch['id'] ?>">
            <input type="hidden" name="child" value="<?= $childId ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn-delete-full">Kustuta see väljakutse</button>
        </form>
    </div>
</div>
</body>
</html>
