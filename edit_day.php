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

$date = $_GET['date'] ?? $_POST['date'] ?? '';
if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header('Location: history.php?child=' . $childId);
    exit;
}

$pdo = get_db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    // Verify the entry actually belongs to this child before touching it.
    $ownCheck = $pdo->prepare("SELECT id FROM entries WHERE id = :id AND child_id = :cid");
    $ownCheck->execute([':id' => $id, ':cid' => $childId]);
    $ownsEntry = (bool) $ownCheck->fetch();

    if ($action === 'delete' && $id > 0 && $ownsEntry) {
        $stmt = $pdo->prepare("DELETE FROM entries WHERE id = :id");
        $stmt->execute([':id' => $id]);
        header('Location: edit_day.php?date=' . urlencode($date) . '&child=' . $childId);
        exit;
    }

    if ($action === 'update' && $id > 0 && $ownsEntry) {
        $type = $_POST['type'] ?? 'mixed';
        $raamat = (int) ($_POST['raamat'] ?? 0);
        $ekraan = (int) ($_POST['ekraan'] ?? 0);
        $bookChoice = $_POST['book_id'] ?? '';
        $newBookTitle = trim($_POST['new_book_title'] ?? '');
        $raamatComment = trim($_POST['raamat_comment'] ?? '');
        $ekraanComment = trim($_POST['ekraan_comment'] ?? '');

        // A single-type entry only keeps its own side.
        if ($type === 'raamat') {
            $ekraan = 0;
            $ekraanComment = '';
        } elseif ($type === 'ekraan') {
            $raamat = 0;
            $raamatComment = '';
            $bookChoice = '';
        }

        if ($raamat <= 0 && $ekraan <= 0) {
            $error = 'Kandel peab olema väärtus suurem kui 0.';
        } elseif ($raamat > 0 && $bookChoice === 'new' && $newBookTitle === '') {
            $error = 'Sisesta uue raamatu pealkiri.';
        } else {
            $bookId = null;
            if ($raamat > 0) {
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

            $stmt = $pdo->prepare("UPDATE entries SET raamat = :raamat, book_id = :book_id, raamat_comment = :raamat_comment, ekraan = :ekraan, ekraan_comment = :ekraan_comment WHERE id = :id");
            $stmt->execute([
                ':raamat' => $raamat,
                ':book_id' => $bookId,
                ':raamat_comment' => $raamat > 0 && $raamatComment !== '' ? $raamatComment : null,
                ':ekraan' => $ekraan,
                ':ekraan_comment' => $ekraan > 0 && $ekraanComment !== '' ? $ekraanComment : null,
                ':id' => $id,
            ]);
            header('Location: edit_day.php?date=' . urlencode($date) . '&child=' . $childId);
            exit;
        }
    }
}

$books = get_books($childId);

$stmt = $pdo->prepare("SELECT * FROM entries WHERE entry_date = :date AND child_id = :cid ORDER BY id ASC");
$stmt->execute([':date' => $date, ':cid' => $childId]);
$dayEntries = $stmt->fetchAll();

$dateLabel = date('d.m.Y', strtotime($date));
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Muuda päeva — Ajaraamat</title>
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
        <a href="history.php?child=<?= $childId ?>" class="link-muted">← Tagasi</a>
    </header>

    <h2 class="day-edit-title"><?= htmlspecialchars($child['name']) ?> — <?= htmlspecialchars($dateLabel) ?></h2>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <?php if (empty($dayEntries)): ?>
        <p class="empty">Sellel päeval pole enam kandeid.</p>
    <?php endif; ?>

    <?php foreach ($dayEntries as $e):
        $eHasR = (int) $e['raamat'] > 0;
        $eHasE = (int) $e['ekraan'] > 0;
        $eMixed = $eHasR && $eHasE;
        $eType = ($eHasE && !$eHasR) ? 'ekraan' : 'raamat';
    ?>
    <div class="card day-entry-card">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
            <input type="hidden" name="child" value="<?= $childId ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $e['id'] ?>">

            <?php if ($eMixed): ?>
                <input type="hidden" name="type" value="mixed">
                <p class="child-link-note" style="text-align:left;margin:0 0 10px;">Vana kanne — sisaldab nii raamatut kui ekraani.</p>
            <?php else: ?>
                <div class="toggle-group">
                    <label class="toggle-btn <?= $eType === 'raamat' ? 'active' : '' ?>">
                        <input type="radio" name="type" value="raamat" <?= $eType === 'raamat' ? 'checked' : '' ?> hidden> 📖 Raamat
                    </label>
                    <label class="toggle-btn <?= $eType === 'ekraan' ? 'active' : '' ?>">
                        <input type="radio" name="type" value="ekraan" <?= $eType === 'ekraan' ? 'checked' : '' ?> hidden> 📱 Ekraan
                    </label>
                </div>
            <?php endif; ?>

            <div class="type-fields" data-type="raamat" <?= ($eMixed || $eType === 'raamat') ? '' : 'hidden' ?>>
                <label for="raamat_<?= $e['id'] ?>">📖 Raamat (min)</label>
                <input type="number" id="raamat_<?= $e['id'] ?>" name="raamat" min="0" value="<?= (int) $e['raamat'] ?>" inputmode="numeric">

                <label for="book_id_<?= $e['id'] ?>">Milline raamat?</label>
                <select id="book_id_<?= $e['id'] ?>" name="book_id" onchange="document.getElementById('new_book_title_<?= $e['id'] ?>').style.display = this.value === 'new' ? 'block' : 'none';">
                    <option value="">— vali raamat —</option>
                    <?php foreach ($books as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= (int) $e['book_id'] === (int) $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?><?= book_status_suffix($b['status']) ?></option>
                    <?php endforeach; ?>
                    <option value="new">+ Uus raamat…</option>
                </select>
                <input type="text" id="new_book_title_<?= $e['id'] ?>" name="new_book_title" placeholder="Uue raamatu pealkiri" style="display:none;margin-top:8px;">

                <label for="raamat_comment_<?= $e['id'] ?>">Märkus (valikuline)</label>
                <input type="text" id="raamat_comment_<?= $e['id'] ?>" name="raamat_comment" value="<?= htmlspecialchars($e['raamat_comment'] ?? '') ?>">
            </div>

            <div class="type-fields" data-type="ekraan" <?= ($eMixed || $eType === 'ekraan') ? '' : 'hidden' ?>>
                <label for="ekraan_<?= $e['id'] ?>">📱 Ekraan (min)</label>
                <input type="number" id="ekraan_<?= $e['id'] ?>" name="ekraan" min="0" value="<?= (int) $e['ekraan'] ?>" inputmode="numeric">

                <label for="ekraan_comment_<?= $e['id'] ?>">Ekraani kommentaar (valikuline)</label>
                <input type="text" id="ekraan_comment_<?= $e['id'] ?>" name="ekraan_comment" value="<?= htmlspecialchars($e['ekraan_comment'] ?? '') ?>">
            </div>

            <div class="day-entry-actions">
                <button type="submit" class="btn btn-add">Salvesta</button>
            </div>
        </form>
        <form method="post" onsubmit="return confirm('Kustutada see kanne täielikult?');" class="day-entry-delete-form">
            <?= csrf_field() ?>
            <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
            <input type="hidden" name="child" value="<?= $childId ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $e['id'] ?>">
            <button type="submit" class="btn-delete-full">Kustuta see kanne</button>
        </form>
    </div>
    <?php endforeach; ?>

    <div class="actions">
        <a href="add.php?child=<?= $childId ?>&date=<?= urlencode($date) ?>" class="btn btn-add full-width">+ Lisa uus kanne sellele päevale</a>
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
