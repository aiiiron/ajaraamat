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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
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
        $pdo = get_db();
        $stmt = $pdo->prepare("INSERT INTO books (child_id, title, author, status, started_date, finished_date, note) VALUES (:cid, :title, :author, :status, :started, :finished, :note)");
        $stmt->execute([
            ':cid' => $childId,
            ':title' => $title,
            ':author' => $author !== '' ? $author : null,
            ':status' => $status,
            ':started' => $started !== '' ? $started : null,
            ':finished' => $status === 'loetud' && $finished !== '' ? $finished : ($status === 'loetud' ? date('Y-m-d') : null),
            ':note' => $note !== '' ? $note : null,
        ]);
        header('Location: books.php?child=' . $childId);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Lisa raamat — Ajaraamat</title>
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
<div class="wrap narrow">
    <header class="topbar">
        <a href="books.php?child=<?= $childId ?>" class="link-muted"><?= icon("arrow-left") ?> Tagasi</a>
    </header>
    <div class="card">
        <h2>Lisa raamat — <?= htmlspecialchars($child['name']) ?></h2>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="child" value="<?= $childId ?>">
            <label for="title">Pealkiri</label>
            <input type="text" id="title" name="title" required autofocus placeholder="nt. Nukitsamees">

            <label for="author">Autor (valikuline)</label>
            <input type="text" id="author" name="author" placeholder="nt. Eno Raud">

            <label for="status">Staatus</label>
            <div class="toggle-group">
                <label class="toggle-btn status-radio">
                    <input type="radio" name="status" value="lugemata" checked style="display:none">
                    Lugemata
                </label>
                <label class="toggle-btn status-radio">
                    <input type="radio" name="status" value="loeb" style="display:none">
                    Loeb praegu
                </label>
                <label class="toggle-btn status-radio">
                    <input type="radio" name="status" value="loetud" style="display:none">
                    Loetud
                </label>
            </div>

            <label for="started_date">Alustatud (valikuline)</label>
            <input type="date" id="started_date" name="started_date">

            <label for="finished_date">Lõpetatud (kui loetud)</label>
            <input type="date" id="finished_date" name="finished_date">

            <label for="note">Kommentaar (valikuline)</label>
            <input type="text" id="note" name="note" placeholder="nt. Väga meeldis!">

            <button type="submit" class="btn btn-add full-width">Salvesta raamat</button>
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
