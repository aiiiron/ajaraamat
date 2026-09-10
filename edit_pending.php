<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$familyId = current_family_id();
$childId = (int) ($_GET['child'] ?? $_POST['child'] ?? 0);
if (!child_belongs_to_family($childId, $familyId)) {
    header('Location: paren.php');
    exit;
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$p = get_pending_entry_for_child($id, $childId);
if (!$p) {
    header('Location: paren.php?child=' . $childId);
    exit;
}

$child = get_child($childId);
$books = get_books($childId);
$currentBooks = array_values(array_filter($books, fn($b) => $b['status'] === 'loeb'));
$error = '';

$type = ($_POST['type'] ?? $p['type']) === 'ekraan' ? 'ekraan' : 'raamat';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? 'approve';

    if ($action === 'reject') {
        reject_pending_entry($id, $childId);
        header('Location: paren.php?child=' . $childId);
        exit;
    }

    $date = $_POST['entry_date'] ?? $p['entry_date'];
    $minutes = (int) ($_POST['minutes'] ?? 0);
    $bookChoice = $_POST['book_id'] ?? '';
    $newBookTitle = trim($_POST['new_book_title'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $currentPage = (int) ($_POST['current_page'] ?? 0);

    if ($minutes <= 0) {
        $error = 'Sisesta minutid (rohkem kui 0).';
    } elseif ($type === 'raamat' && $bookChoice === 'new' && $newBookTitle === '') {
        $error = 'Sisesta uue raamatu pealkiri.';
    } else {
        $bookId = null;
        if ($type === 'raamat') {
            if ($bookChoice === 'new' && $newBookTitle !== '') {
                $bookId = create_book($childId, $newBookTitle);
            } elseif ($bookChoice !== '' && $bookChoice !== 'new') {
                $bookId = (int) $bookChoice;
            }
        }
        approve_pending_entry_with_edits($id, $childId, $date, $type, $minutes, $bookId, $note, $currentPage);
        header('Location: paren.php?child=' . $childId . '&saved=1');
        exit;
    }
}

$date = $_POST['entry_date'] ?? $p['entry_date'];
$minutes = $_POST['minutes'] ?? $p['minutes'];
$bookId = $_POST['book_id'] ?? ($p['book_id'] ?: '');
$note = $_POST['note'] ?? ($p['note'] ?? '');
$currentPage = $_POST['current_page'] ?? ($p['current_page'] ?? '');
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Muuda kannet — Ajaraamat</title>
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
        <a href="paren.php?child=<?= $childId ?>" class="link-muted"><?= icon("arrow-left") ?> Tagasi</a>
    </header>
    <div class="card">
        <h2>Muuda kannet — <?= htmlspecialchars($child['name']) ?></h2>
        <p class="child-link-note" style="text-align:left;margin:0 0 12px;"><?= htmlspecialchars($child['name']) ?> lisas selle ise<?= $p['source'] === 'taimer' ? ' taimeriga' : '' ?>. Paranda vajadusel ja kinnita.</p>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="child" value="<?= $childId ?>">
            <input type="hidden" name="action" value="approve">

            <label for="entry_date">Kuupäev</label>
            <input type="date" id="entry_date" name="entry_date" value="<?= htmlspecialchars($date) ?>" required>

            <label>Mille kohta on kanne?</label>
            <div class="toggle-group" id="type-toggle">
                <label class="toggle-btn <?= $type === 'raamat' ? 'active' : '' ?>">
                    <input type="radio" name="type" value="raamat" <?= $type === 'raamat' ? 'checked' : '' ?> hidden> <?= emoji_svg('books') ?> Raamat
                </label>
                <label class="toggle-btn <?= $type === 'ekraan' ? 'active' : '' ?>">
                    <input type="radio" name="type" value="ekraan" <?= $type === 'ekraan' ? 'checked' : '' ?> hidden> <?= emoji_svg('screen') ?> Ekraan
                </label>
            </div>

            <label for="minutes"><?= emoji_svg($type === 'raamat' ? 'books' : 'screen') ?> Minutid</label>
            <input type="number" id="minutes" name="minutes" min="1" max="600" inputmode="numeric" value="<?= htmlspecialchars((string) $minutes) ?>">

            <div class="type-fields" data-type="raamat" <?= $type === 'raamat' ? '' : 'hidden' ?>>
                <label for="book_id">Milline raamat?</label>
                <select id="book_id" name="book_id" onchange="document.getElementById('new_book_title').style.display = this.value === 'new' ? 'block' : 'none';">
                    <option value="">— vali raamat —</option>
                    <?php foreach ($books as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= (string) $bookId === (string) $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?><?= book_status_suffix($b['status']) ?></option>
                    <?php endforeach; ?>
                    <option value="new" <?= $bookId === 'new' ? 'selected' : '' ?>>+ Uus raamat…</option>
                </select>
                <input type="text" id="new_book_title" name="new_book_title" placeholder="Uue raamatu pealkiri" value="<?= htmlspecialchars($_POST['new_book_title'] ?? '') ?>" style="display:<?= $bookId === 'new' ? 'block' : 'none' ?>;margin-top:8px;">
                <?php if (!empty($currentBooks)): ?>
                <div class="quick-add-row">
                    <?php foreach (array_slice($currentBooks, 0, 4) as $b): ?>
                        <button type="button" class="quick-add-btn" onclick="document.getElementById('book_id').value='<?= $b['id'] ?>';document.getElementById('new_book_title').style.display='none';"><?= emoji_svg('books') ?> <?= htmlspecialchars($b['title']) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <label for="current_page">Praegu leheküljel (valikuline)</label>
                <input type="number" id="current_page" name="current_page" min="0" inputmode="numeric" value="<?= htmlspecialchars((string) $currentPage) ?>">
            </div>

            <label for="note">Märkus (valikuline)</label>
            <input type="text" id="note" name="note" value="<?= htmlspecialchars((string) $note) ?>">

            <button type="submit" class="btn btn-add full-width">Salvesta ja kinnita</button>
        </form>
        <form method="post" onsubmit="return confirm('Lükata see kanne tagasi ilma salvestamata?');" class="day-entry-delete-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="child" value="<?= $childId ?>">
            <input type="hidden" name="action" value="reject">
            <button type="submit" class="btn-delete-full">Lükka tagasi</button>
        </form>
    </div>
</div>
<script>
document.querySelectorAll('.toggle-group').forEach(function (group) {
    var scope = group.closest('form') || document;
    group.querySelectorAll('input[type=radio]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var val = group.querySelector('input:checked').value;
            group.querySelectorAll('.toggle-btn').forEach(function (btn) {
                btn.classList.toggle('active', btn.querySelector('input').value === val);
            });
            scope.querySelectorAll('.type-fields').forEach(function (f) {
                f.hidden = f.dataset.type !== val;
            });
        });
    });
});
</script>
</body>
</html>
