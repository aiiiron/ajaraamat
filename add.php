<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$familyId = current_family_id();
$childId = (int) ($_GET['child'] ?? $_POST['child'] ?? 0);
if (!child_belongs_to_family($childId, $familyId)) {
    header('Location: children.php');
    exit;
}
$child = get_child($childId);
$books = get_books($childId);

$error = '';
$fromDay = $_GET['date'] ?? $_POST['from_day'] ?? '';
$recentEkraan = array_column(get_top_comments($childId, 'ekraan', 4), 'comment');

$type = ($_POST['type'] ?? $_GET['type'] ?? 'raamat') === 'ekraan' ? 'ekraan' : 'raamat';
$currentBooks = array_values(array_filter($books, fn($b) => $b['status'] === 'loeb'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $date = $_POST['entry_date'] ?? date('Y-m-d');
    $raamat = $type === 'raamat' ? (int) ($_POST['raamat'] ?? 0) : 0;
    $ekraan = $type === 'ekraan' ? (int) ($_POST['ekraan'] ?? 0) : 0;
    $bookChoice = $_POST['book_id'] ?? '';
    $newBookTitle = trim($_POST['new_book_title'] ?? '');
    $raamatComment = trim($_POST['raamat_comment'] ?? '');
    $ekraanComment = trim($_POST['ekraan_comment'] ?? '');

    if ($type === 'raamat' && $raamat <= 0) {
        $error = 'Sisesta raamatuminutid (rohkem kui 0).';
    } elseif ($type === 'ekraan' && $ekraan <= 0) {
        $error = 'Sisesta ekraaniminutid (rohkem kui 0).';
    } elseif ($type === 'raamat' && $bookChoice === 'new' && $newBookTitle === '') {
        $error = 'Sisesta uue raamatu pealkiri.';
    } else {
        $bookId = null;
        if ($type === 'raamat') {
            if ($bookChoice === 'new' && $newBookTitle !== '') {
                $bookId = create_book($childId, $newBookTitle);
            } elseif ($bookChoice !== '' && $bookChoice !== 'new') {
                $chosen = get_book_for_child((int) $bookChoice, $childId);
                if ($chosen) {
                    $bookId = (int) $chosen['id'];
                    mark_book_started($bookId);
                }
            }
        }

        $pdo = get_db();
        $stmt = $pdo->prepare("INSERT INTO entries (child_id, entry_date, raamat, book_id, raamat_comment, ekraan, ekraan_comment) VALUES (:cid, :date, :raamat, :book_id, :raamat_comment, :ekraan, :ekraan_comment)");
        $stmt->execute([
            ':cid' => $childId,
            ':date' => $date,
            ':raamat' => $raamat,
            ':book_id' => $bookId,
            ':raamat_comment' => $type === 'raamat' && $raamatComment !== '' ? $raamatComment : null,
            ':ekraan' => $ekraan,
            ':ekraan_comment' => $type === 'ekraan' && $ekraanComment !== '' ? $ekraanComment : null,
        ]);
        if ($fromDay !== '') {
            header('Location: edit_day.php?date=' . urlencode($fromDay) . '&child=' . $childId);
        } else {
            header('Location: paren.php?child=' . $childId . '&saved=1');
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lisa kanne — Ajaraamat</title>
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" href="icon-192.png" type="image/png">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#8B5CF6">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>">
</head>
<body>
<div class="wrap narrow">
    <header class="topbar">
        <a href="<?= $fromDay !== '' ? 'edit_day.php?date=' . urlencode($fromDay) . '&child=' . $childId : 'paren.php?child=' . $childId ?>" class="link-muted">← Tagasi</a>
    </header>
    <div class="card">
        <h2>Lisa kanne — <?= htmlspecialchars($child['name']) ?></h2>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="child" value="<?= $childId ?>">
            <input type="hidden" name="from_day" value="<?= htmlspecialchars($fromDay) ?>">

            <label for="entry_date">Kuupäev</label>
            <input type="date" id="entry_date" name="entry_date" value="<?= htmlspecialchars($_POST['entry_date'] ?? $_GET['date'] ?? date('Y-m-d')) ?>" required>

            <label>Mille kohta on kanne?</label>
            <div class="toggle-group" id="type-toggle">
                <label class="toggle-btn <?= $type === 'raamat' ? 'active' : '' ?>">
                    <input type="radio" name="type" value="raamat" <?= $type === 'raamat' ? 'checked' : '' ?> hidden> 📖 Raamat
                </label>
                <label class="toggle-btn <?= $type === 'ekraan' ? 'active' : '' ?>">
                    <input type="radio" name="type" value="ekraan" <?= $type === 'ekraan' ? 'checked' : '' ?> hidden> 📱 Ekraan
                </label>
            </div>

            <div class="type-fields" data-type="raamat" <?= $type === 'raamat' ? '' : 'hidden' ?>>
                <label for="raamat">📖 Raamat (min)</label>
                <input type="number" id="raamat" name="raamat" min="0" placeholder="nt. 30" inputmode="numeric" value="<?= htmlspecialchars($_POST['raamat'] ?? '') ?>" <?= $type === 'raamat' ? 'autofocus' : '' ?>>

                <label for="book_id">Milline raamat?</label>
                <select id="book_id" name="book_id" onchange="document.getElementById('new_book_title').style.display = this.value === 'new' ? 'block' : 'none';">
                    <option value="">— vali raamat —</option>
                    <?php foreach ($books as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= ($_POST['book_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?><?= book_status_suffix($b['status']) ?></option>
                    <?php endforeach; ?>
                    <option value="new" <?= ($_POST['book_id'] ?? '') === 'new' ? 'selected' : '' ?>>+ Uus raamat…</option>
                </select>
                <input type="text" id="new_book_title" name="new_book_title" placeholder="Uue raamatu pealkiri" value="<?= htmlspecialchars($_POST['new_book_title'] ?? '') ?>" style="display:<?= ($_POST['book_id'] ?? '') === 'new' ? 'block' : 'none' ?>;margin-top:8px;">
                <?php if (!empty($currentBooks)): ?>
                <div class="quick-add-row">
                    <?php foreach (array_slice($currentBooks, 0, 4) as $b): ?>
                        <button type="button" class="quick-add-btn" onclick="var s=document.getElementById('book_id');s.value='<?= $b['id'] ?>';document.getElementById('new_book_title').style.display='none';document.getElementById('raamat').focus();">📖 <?= htmlspecialchars($b['title']) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <label for="raamat_comment">Märkus (valikuline)</label>
                <input type="text" id="raamat_comment" name="raamat_comment" placeholder="nt. hea peatükk!" value="<?= htmlspecialchars($_POST['raamat_comment'] ?? '') ?>">
            </div>

            <div class="type-fields" data-type="ekraan" <?= $type === 'ekraan' ? '' : 'hidden' ?>>
                <label for="ekraan">📱 Ekraan (min)</label>
                <input type="number" id="ekraan" name="ekraan" min="0" placeholder="nt. 30" inputmode="numeric" value="<?= htmlspecialchars($_POST['ekraan'] ?? '') ?>" <?= $type === 'ekraan' ? 'autofocus' : '' ?>>

                <label for="ekraan_comment">Ekraani kommentaar (valikuline)</label>
                <input type="text" id="ekraan_comment" name="ekraan_comment" placeholder="nt. Youtube, Operatsioon AI" value="<?= htmlspecialchars($_POST['ekraan_comment'] ?? '') ?>">
                <?php if (!empty($recentEkraan)): ?>
                <div class="quick-add-row">
                    <?php foreach ($recentEkraan as $c): ?>
                        <button type="button" class="quick-add-btn" onclick="document.getElementById('ekraan_comment').value=<?= json_encode($c) ?>;document.getElementById('ekraan').focus();">📱 <?= htmlspecialchars($c) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-add full-width">Salvesta kanne</button>
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
