<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['family_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id > 0 && in_array($action, ['approved', 'rejected', 'pending'], true)) {
        set_family_status($id, $action);
    }
    header('Location: admin.php');
    exit;
}

$pending = get_families_by_status('pending');
$all = get_all_families();
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Ajaraamat</title>
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrap">
    <header class="topbar">
        <h1>🛠 Admin</h1>
        <a href="admin_logout.php" class="link-muted">Logi välja</a>
    </header>

    <section class="card">
        <h2>Ootel registreerimised (<?= count($pending) ?>)</h2>
        <?php if (empty($pending)): ?>
            <p class="empty">Ootel taotlusi pole.</p>
        <?php else: ?>
            <?php foreach ($pending as $f): ?>
                <div class="day-entry-card" style="margin-bottom:12px;">
                    <p style="margin-bottom:4px;"><strong><?= htmlspecialchars($f['email']) ?></strong></p>
                    <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px;">Registreeris: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($f['created_at']))) ?></p>
                    <div style="display:flex;gap:10px;">
                        <form method="post" style="flex:1;">
                            <input type="hidden" name="family_id" value="<?= $f['id'] ?>">
                            <input type="hidden" name="action" value="approved">
                            <button type="submit" class="btn btn-add full-width">Kinnita</button>
                        </form>
                        <form method="post" style="flex:1;">
                            <input type="hidden" name="family_id" value="<?= $f['id'] ?>">
                            <input type="hidden" name="action" value="rejected">
                            <button type="submit" class="btn-delete-full">Lükka tagasi</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Kõik pered (<?= count($all) ?>)</h2>
        <div class="table-scroll">
        <table class="entries-table">
            <thead>
                <tr><th>E-post</th><th>Staatus</th><th>Lapsi</th><th>Registreeris</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($all as $f): ?>
                <tr>
                    <td><?= htmlspecialchars($f['email']) ?></td>
                    <td>
                        <?php if ($f['status'] === 'approved'): ?>
                            <span class="tag tag-reading">Kinnitatud</span>
                        <?php elseif ($f['status'] === 'pending'): ?>
                            <span class="tag tag-screen">Ootel</span>
                        <?php else: ?>
                            <span class="tag" style="background:#fee2e2;color:#b91c1c;">Tagasi lükatud</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $f['child_count'] ?></td>
                    <td><?= htmlspecialchars(date('d.m.Y', strtotime($f['created_at']))) ?></td>
                    <td>
                        <?php if ($f['status'] !== 'approved'): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="family_id" value="<?= $f['id'] ?>">
                            <input type="hidden" name="action" value="approved">
                            <button type="submit" class="btn-edit" style="border:none;background:none;cursor:pointer;">Kinnita</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($f['status'] !== 'rejected'): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="family_id" value="<?= $f['id'] ?>">
                            <input type="hidden" name="action" value="rejected">
                            <button type="submit" class="btn-delete" style="cursor:pointer;">Blokeeri</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
</div>
</body>
</html>
