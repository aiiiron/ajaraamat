<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'reset_cancel') {
        $resetId = (int) ($_POST['reset_id'] ?? 0);
        if ($resetId > 0) {
            get_db()->prepare("DELETE FROM password_resets WHERE id = :id")->execute([':id' => $resetId]);
        }
    } elseif ($action === 'set_plan') {
        $id = (int) ($_POST['family_id'] ?? 0);
        if ($id > 0) {
            set_family_plan($id, ($_POST['plan'] ?? '') === 'pere_plus' ? 'pere_plus' : 'free');
        }
    } elseif ($action === 'toggle_feature') {
        set_feature_premium((string) ($_POST['flag_id'] ?? ''), !empty($_POST['is_premium']));
    } else {
        $id = (int) ($_POST['family_id'] ?? 0);
        if ($id > 0 && in_array($action, ['approved', 'rejected', 'pending'], true)) {
            set_family_status($id, $action);
        }
    }
    header('Location: admin.php');
    exit;
}

$pending = get_families_by_status('pending');
$all = get_all_families();
$resets = get_active_password_resets();
$featureFlags = get_feature_flags();
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Admin — Ajaraamat</title>
<link rel="icon" href="favicon.ico?v=2" sizes="any">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>">
</head>
<body>
<div class="wrap">
    <header class="topbar">
        <h1>🛠 Admin</h1>
        <div class="header-actions">
            <?php if (!empty($_SESSION['family_id'])): ?>
                <a href="paren.php" class="link-muted">← Töölaud</a>
                <a href="logout.php" class="link-muted">Logi välja</a>
            <?php else: ?>
                <a href="admin_logout.php" class="link-muted">Logi välja</a>
            <?php endif; ?>
        </div>
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
                            <?= csrf_field() ?>
                            <input type="hidden" name="family_id" value="<?= $f['id'] ?>">
                            <input type="hidden" name="action" value="approved">
                            <button type="submit" class="btn btn-add full-width">Kinnita</button>
                        </form>
                        <form method="post" style="flex:1;">
                            <?= csrf_field() ?>
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
        <h2>Parooli lähtestamise päringud (<?= count($resets) ?>)</h2>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px;">
            Link saadetakse vanemale automaatselt e-postiga, aga jagatud hostingu kiri
            ei pruugi kohale jõuda. Kui vanem ütleb, et kirja ei tulnud, kopeeri talle
            see link käsitsi. Iga link kehtib 24 tundi ja toimib ainult ühe korra.
        </p>
        <?php if (empty($resets)): ?>
            <p class="empty">Aktiivseid päringuid pole.</p>
        <?php else: ?>
            <?php foreach ($resets as $r): ?>
                <?php $link = base_url() . '/reset.php?token=' . $r['token']; ?>
                <div class="day-entry-card" style="margin-bottom:12px;">
                    <p style="margin-bottom:4px;"><strong><?= htmlspecialchars($r['email']) ?></strong></p>
                    <p style="font-size:13px;color:var(--text-muted);margin-bottom:8px;">
                        Küsitud: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['created_at']))) ?>
                        · aegub: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['expires_at']))) ?>
                    </p>
                    <input type="text" readonly value="<?= htmlspecialchars($link, ENT_QUOTES) ?>"
                           onclick="this.select()" style="width:100%;margin-bottom:8px;font-size:13px;">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reset_cancel">
                        <input type="hidden" name="reset_id" value="<?= (int) $r['id'] ?>">
                        <button type="submit" class="btn-delete-full">Tühista see päring</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Pere+ funktsioonid</h2>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px;">
            Lülita, millised funktsioonid on hetkel Pere+ jaoks (lülita välja, et teha
            kõigile tasuta — nt kui katsetad hinnastust või piirad ajutiselt midagi muud).
        </p>
        <?php foreach ($featureFlags as $flag): ?>
            <form method="post" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 0;border-bottom:1px solid var(--border);">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_feature">
                <input type="hidden" name="flag_id" value="<?= htmlspecialchars($flag['id']) ?>">
                <input type="hidden" name="is_premium" value="<?= $flag['is_premium'] ? '0' : '1' ?>">
                <span style="font-size:14px;"><?= htmlspecialchars($flag['label']) ?></span>
                <button type="submit" class="tag <?= $flag['is_premium'] ? 'tag-reading' : '' ?>" style="border:none;cursor:pointer;white-space:nowrap;">
                    <?= $flag['is_premium'] ? '🌟 Pere+' : 'Tasuta kõigile' ?>
                </button>
            </form>
        <?php endforeach; ?>
    </section>

    <section class="card">
        <h2>Kõik pered (<?= count($all) ?>)</h2>
        <div class="table-scroll">
        <table class="entries-table">
            <thead>
                <tr><th>E-post</th><th>Staatus</th><th>Plaan</th><th>Lapsi</th><th>Registreeris</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($all as $f): ?>
                <tr>
                    <td><?= htmlspecialchars($f['email']) ?><?php if (!empty($f['is_demo'])): ?> <span class="tag" style="background:#FFF3D0;color:#93450A;">demo</span><?php endif; ?></td>
                    <td>
                        <?php if ($f['status'] === 'approved'): ?>
                            <span class="tag tag-reading">Kinnitatud</span>
                        <?php elseif ($f['status'] === 'pending'): ?>
                            <span class="tag tag-screen">Ootel</span>
                        <?php else: ?>
                            <span class="tag" style="background:#fee2e2;color:#b91c1c;">Tagasi lükatud</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" style="display:flex;gap:6px;align-items:center;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_plan">
                            <input type="hidden" name="family_id" value="<?= $f['id'] ?>">
                            <select name="plan" onchange="this.form.submit()" style="width:auto;min-height:0;padding:4px 8px;font-size:12px;">
                                <option value="free" <?= ($f['plan'] ?? 'free') === 'free' ? 'selected' : '' ?>>Free</option>
                                <option value="pere_plus" <?= ($f['plan'] ?? 'free') === 'pere_plus' ? 'selected' : '' ?>>Pere+</option>
                            </select>
                        </form>
                    </td>
                    <td><?= $f['child_count'] ?></td>
                    <td><?= htmlspecialchars(date('d.m.Y', strtotime($f['created_at']))) ?></td>
                    <td>
                        <?php if (!empty($f['is_demo'])): ?>
                        <a href="demo_parent.php" target="_blank" rel="noopener" style="margin-right:10px;">Vanem →</a>
                        <a href="demo_child.php" target="_blank" rel="noopener" style="margin-right:10px;">Laps →</a>
                        <a href="seed_demo.php" target="_blank" rel="noopener" style="margin-right:10px;">Taasta →</a>
                        <?php endif; ?>
                        <?php if ($f['status'] !== 'approved'): ?>
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="family_id" value="<?= $f['id'] ?>">
                            <input type="hidden" name="action" value="approved">
                            <button type="submit" class="btn-edit" style="border:none;background:none;cursor:pointer;">Kinnita</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($f['status'] !== 'rejected'): ?>
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
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
