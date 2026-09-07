<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$familyId = current_family_id();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: children.php');
    exit;
}

$pdo = get_db();

// Load the book and verify its child belongs to the logged-in family.
$stmt = $pdo->prepare("SELECT b.*, c.family_id, c.name as child_name FROM books b JOIN children c ON c.id = b.child_id WHERE b.id = :id");
$stmt->execute([':id' => $id]);
$book = $stmt->fetch();
if (!$book || (int) $book['family_id'] !== $familyId) {
    header('Location: children.php');
    exit;
}
$childId = (int) $book['child_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM books WHERE id = :id");
    $stmt->execute([':id' => $id]);
    header('Location: books.php?child=' . $childId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $statusIn = $_POST['status'] ?? 'lugemata';
    $status = in_array($statusIn, ['lugemata', 'loeb', 'loetud'], true) ? $statusIn : 'lugemata';
    $started = $_POST['started_date'] ?? '';
    $finished = $_POST['finished_date'] ?? '';
    $note = trim($_POST['note'] ?? '');

    if ($title === '') {
        $error = 'Sisesta raamatu pealkiri.';
    } else {
        $stmt = $pdo->prepare("UPDATE books SET title = :title, author = :author, status = :status, started_date = :started, finished_date = :finished, note = :note WHERE id = :id");
        $stmt->execute([
            ':title' => $title,
            ':author' => $author !== '' ? $author : null,
            ':status' => $status,
            ':started' => $started !== '' ? $started : null,
            ':finished' => $finished !== '' ? $finished : null,
            ':note' => $note !== '' ? $note : null,
            ':id' => $id,
        ]);
        header('Location: books.php?child=' . $childId);
        exit;
    }
}

$title = $_POST['title'] ?? $book['title'];
$author = $_POST['author'] ?? $book['author'];
$status = $_POST['status'] ?? $book['status'];
$started = $_POST['started_date'] ?? $book['started_date'];
$finished = $_POST['finished_date'] ?? $book['finished_date'];
$note = $_POST['note'] ?? $book['note'];
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Muuda raamatut — Ajaraamat</title>
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
        <a href="books.php?child=<?= $childId ?>" class="link-muted">← Tagasi</a>
    </header>
    <div class="card">
        <h2>Muuda raamatut — <?= htmlspecialchars($book['child_name']) ?></h2>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="id" value="<?= $book['id'] ?>">

            <label for="title">Pealkiri</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>" required>

            <label for="author">Autor (valikuline)</label>
            <input type="text" id="author" name="author" value="<?= htmlspecialchars($author ?? '') ?>">

            <label for="status">Staatus</label>
            <div class="toggle-group">
                <label class="toggle-btn status-radio">
                    <input type="radio" name="status" value="lugemata" <?= $status === 'lugemata' ? 'checked' : '' ?> style="display:none">
                    Lugemata
                </label>
                <label class="toggle-btn status-radio">
                    <input type="radio" name="status" value="loeb" <?= $status === 'loeb' ? 'checked' : '' ?> style="display:none">
                    Loeb praegu
                </label>
                <label class="toggle-btn status-radio">
                    <input type="radio" name="status" value="loetud" <?= $status === 'loetud' ? 'checked' : '' ?> style="display:none">
                    Loetud
                </label>
            </div>

            <label for="started_date">Alustatud (valikuline)</label>
            <input type="date" id="started_date" name="started_date" value="<?= htmlspecialchars($started ?? '') ?>">

            <label for="finished_date">Lõpetatud (kui loetud)</label>
            <input type="date" id="finished_date" name="finished_date" value="<?= htmlspecialchars($finished ?? '') ?>">

            <label for="note">Kommentaar (valikuline)</label>
            <input type="text" id="note" name="note" value="<?= htmlspecialchars($note ?? '') ?>">

            <button type="submit" class="btn btn-add full-width">Salvesta muudatused</button>
        </form>
        <form method="post" onsubmit="return confirm('Kustutada see raamat?');" class="day-entry-delete-form">
            <input type="hidden" name="id" value="<?= $book['id'] ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn-delete-full">Kustuta see raamat</button>
        </form>
    </div>
</div>
<script>
document.querySelectorAll('.status-radio').forEach(function(label) {
    var input = label.querySelector('input');
    function sync() {
        document.querySelectorAll('.status-radio').forEach(function(l) { l.classList.remove('active'); });
        if (input.checked) label.classList.add('active');
    }
    input.addEventListener('change', sync);
    sync();
});
</script>
</body>
</html>
