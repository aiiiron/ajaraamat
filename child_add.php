<?php
// Public, token-only. The child adds an entry; it goes into pending_entries
// and a parent approves it from the dashboard.
require_once __DIR__ . '/functions.php';

$token = $_GET['token'] ?? '';
$child = $token !== '' ? get_child_by_token($token) : null;

if (!$child) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="et"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ei leitud · Ajaraamat</title><link rel="preconnect" href="https://fonts.googleapis.com">
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

if (!has_feature((int) $child['family_id'], 'child_self_log')) {
    ?>
    <!DOCTYPE html>
    <html lang="et"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ajaraamat</title><link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>"></head>
    <body><div class="wrap narrow">
    <header class="topbar"><a href="child.php?token=<?= htmlspecialchars($token) ?>" class="link-muted"><?= icon("arrow-left") ?> Tagasi</a></header>
    <div class="card" style="text-align:center;"><p>See funktsioon pole praegu saadaval. Palu vanemal lisada see kanne sinu eest.</p></div>
    </div></body></html>
    <?php
    exit;
}

$books = get_books($childId);
$currentBooks = array_values(array_filter($books, fn($b) => $b['status'] === 'loeb'));
$error = '';

$type = ($_POST['type'] ?? 'raamat') === 'ekraan' ? 'ekraan' : 'raamat';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $minutes = (int) ($_POST['minutes'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    $bookChoice = $_POST['book_id'] ?? '';
    $newBookTitle = trim($_POST['new_book_title'] ?? '');
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
                $chosen = get_book_for_child((int) $bookChoice, $childId);
                if ($chosen) $bookId = (int) $chosen['id'];
            }
        }
        add_pending_entry($childId, date('Y-m-d'), $type, $minutes, $bookId, $note, $currentPage, null);
        header('Location: child.php?token=' . urlencode($token) . '&pending=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Lisa kanne · Ajaraamat</title>
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
<div class="wrap narrow">
    <header class="topbar">
        <a href="child.php?token=<?= htmlspecialchars($token) ?>" class="link-muted"><?= icon("arrow-left") ?> Tagasi</a>
    </header>
    <div class="card">
        <h2>Lisa kanne ise</h2>
        <p class="child-link-note" style="text-align:left;margin:0 0 12px;">Kanne läheb vanemale kinnitamiseks.</p>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="post">
            <label>Mille kohta on kanne?</label>
            <div class="toggle-group">
                <label class="toggle-btn <?= $type === 'raamat' ? 'active' : '' ?>">
                    <input type="radio" name="type" value="raamat" <?= $type === 'raamat' ? 'checked' : '' ?> hidden> <?= emoji_svg('books') ?> Raamat
                </label>
                <label class="toggle-btn <?= $type === 'ekraan' ? 'active' : '' ?>">
                    <input type="radio" name="type" value="ekraan" <?= $type === 'ekraan' ? 'checked' : '' ?> hidden> <?= emoji_svg('screen') ?> Ekraan
                </label>
            </div>

            <label for="minutes">Mitu minutit?</label>
            <input type="number" id="minutes" name="minutes" min="1" max="600" inputmode="numeric" placeholder="nt. 20" value="<?= htmlspecialchars($_POST['minutes'] ?? '') ?>" autofocus>

            <div class="type-fields" data-type="raamat" <?= $type === 'raamat' ? '' : 'hidden' ?>>
                <label for="book_id">Milline raamat?</label>
                <select id="book_id" name="book_id" onchange="document.getElementById('new_book_title').style.display = this.value === 'new' ? 'block' : 'none';">
                    <option value="">Vali raamat…</option>
                    <?php foreach ($books as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= ($_POST['book_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?><?= book_status_suffix($b['status']) ?></option>
                    <?php endforeach; ?>
                    <option value="new" <?= ($_POST['book_id'] ?? '') === 'new' ? 'selected' : '' ?>>+ Uus raamat…</option>
                </select>
                <input type="text" id="new_book_title" name="new_book_title" placeholder="Uue raamatu pealkiri" value="<?= htmlspecialchars($_POST['new_book_title'] ?? '') ?>" style="display:<?= ($_POST['book_id'] ?? '') === 'new' ? 'block' : 'none' ?>;margin-top:8px;">
                <?php if (!empty($currentBooks)): ?>
                <div class="quick-add-row">
                    <?php foreach (array_slice($currentBooks, 0, 4) as $b): ?>
                        <button type="button" class="quick-add-btn" onclick="var s=document.getElementById('book_id');s.value='<?= $b['id'] ?>';document.getElementById('new_book_title').style.display='none';document.getElementById('minutes').focus();"><?= emoji_svg('books') ?> <?= htmlspecialchars($b['title']) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <label for="current_page">Praegu leheküljel (valikuline)</label>
                <input type="number" id="current_page" name="current_page" min="0" inputmode="numeric" placeholder="nt. 84" value="<?= htmlspecialchars($_POST['current_page'] ?? '') ?>">
            </div>

            <label for="note">Märkus (valikuline)</label>
            <input type="text" id="note" name="note" placeholder="nt. hea peatükk!" value="<?= htmlspecialchars($_POST['note'] ?? '') ?>">

            <button type="submit" class="btn btn-add full-width">Saada kinnitamiseks</button>
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
