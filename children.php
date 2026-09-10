<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$familyId = current_family_id();
$isDemo = is_demo_session();
$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if (isset($_POST['delete_id'])) {
        $childId = (int) $_POST['delete_id'];
        if (child_belongs_to_family($childId, $familyId)) {
            delete_child($childId);
        }
        header('Location: children.php');
        exit;
    } elseif ($action === 'goal') {
        $cid = (int) ($_POST['child_id'] ?? 0);
        if (child_belongs_to_family($cid, $familyId)) {
            $d = (int) ($_POST['daily_goal_min'] ?? 0);
            $w = (int) ($_POST['weekly_goal_min'] ?? 0);
            $r = (int) ($_POST['screen_reward_cap_min'] ?? 0);
            $ratio = 0.0;
            if (has_feature($familyId, 'custom_reading_ratio')) {
                $ratio = (float) str_replace(',', '.', (string) ($_POST['reading_ratio'] ?? ''));
                $ratio = $ratio > 0 ? min(9.99, round($ratio, 2)) : 0.0;
            }
            set_child_goal($cid, $d > 0 ? $d : null, $w > 0 ? $w : null, $r > 0 ? $r : null, $ratio > 0 ? $ratio : null);
        }
        header('Location: children.php');
        exit;
    } elseif ($action === 'add_login') {
        if ($isDemo) {
            $error = 'Demo režiimis ei saa kontosid muuta.';
        } elseif (!has_feature($familyId, 'second_parent_login')) {
            $error = 'Teise vanema lisamine on Pere+ pere jaoks.';
        } else {
            $email = trim($_POST['email'] ?? '');
            $name  = trim($_POST['name'] ?? '');
            $pw    = $_POST['password'] ?? '';
            $pw2   = $_POST['password_confirm'] ?? '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Sisesta kehtiv e-posti aadress.';
            } elseif (strlen($pw) < 6) {
                $error = 'Parool peab olema vähemalt 6 tähemärki.';
            } elseif ($pw !== $pw2) {
                $error = 'Paroolid ei kattu.';
            } elseif (parent_email_exists($email)) {
                $error = 'See e-post on juba kasutusel.';
            } else {
                add_family_login($familyId, $email, $name, $pw);
                header('Location: children.php?added=1');
                exit;
            }
        }
    } elseif ($action === 'del_login') {
        if (!$isDemo) {
            delete_family_login((int) ($_POST['login_id'] ?? 0), $familyId);
        }
        header('Location: children.php?removed=1');
        exit;
    } elseif ($action === 'change_pw') {
        if ($isDemo) {
            $error = 'Demo režiimis ei saa kontosid muuta.';
        } else {
            $cur  = $_POST['current_password'] ?? '';
            $new  = $_POST['new_password'] ?? '';
            $new2 = $_POST['new_password_confirm'] ?? '';
            if (strlen($new) < 6) {
                $error = 'Uus parool peab olema vähemalt 6 tähemärki.';
            } elseif ($new !== $new2) {
                $error = 'Uued paroolid ei kattu.';
            } elseif (!change_current_password($cur, $new)) {
                $error = 'Praegune parool on vale.';
            } else {
                header('Location: children.php?pw=1');
                exit;
            }
        }
    } else {
        // "Lisa uus laps" form — no `action` field.
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $error = 'Sisesta lapse nimi.';
        } elseif (count(get_children($familyId)) >= 1 && !has_feature($familyId, 'multi_child')) {
            $error = 'Rohkem kui 1 laps on Pere+ pere jaoks.';
        } else {
            create_child($familyId, $name);
            header('Location: children.php');
            exit;
        }
    }
}

if (($_GET['added'] ?? '') === '1')   $notice = 'Teine vanem lisatud. Anna talle e-post ja parool ise edasi.';
if (($_GET['removed'] ?? '') === '1') $notice = 'Vanema ligipääs eemaldatud.';
if (($_GET['pw'] ?? '') === '1')      $notice = 'Parool muudetud.';

$children = get_children($familyId);
$family = get_family($familyId);
$logins = get_family_logins($familyId);
$canMultiChild = has_feature($familyId, 'multi_child');
$canCustomRatio = has_feature($familyId, 'custom_reading_ratio');
$canSecondParent = has_feature($familyId, 'second_parent_login');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$baseUrl = $scheme . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Pere — Ajaraamat</title>
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
            <a href="children.php" class="link-muted current" aria-current="page">Pere</a>
            <?php if (is_admin()): ?>
                <a href="admin.php" class="link-muted">Admin</a>
            <?php endif; ?>
            <a href="logout.php" class="link-muted">Logi välja</a>
        </div>
    </header>

    <nav class="tabs">
        <a href="paren.php" class="tab">Töölaud</a>
        <a href="history.php" class="tab">Kanded</a>
        <a href="books.php" class="tab">Raamatud</a>
        <a href="milestones.php" class="tab">Saavutused</a>
    </nav>

    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($notice): ?><div class="milestone-banner"><?= icon("check") ?> <?= htmlspecialchars($notice) ?></div><?php endif; ?>

    <div class="actions">
        <a href="overview.php" class="btn btn-outline">📊 Kõik lapsed korraga</a>
        <a href="trash.php" class="btn btn-outline">🗑️ Kustutatud kanded</a>
    </div>

    <?php foreach ($children as $c):
        $link = $baseUrl . '/child.php?token=' . $c['public_token'];
    ?>
    <div class="card">
        <div class="card-header">
            <h2><?= htmlspecialchars($c['name']) ?></h2>
        </div>
        <p style="font-size:14px;color:var(--text-muted);margin-bottom:8px;">
            Lapse enda link (ilma sisselogimiseta) — jaga seda lapse enda seadmesse järjehoidjaks:
        </p>
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:14px;">
            <input type="text" readonly value="<?= htmlspecialchars($link) ?>" onclick="this.select();" style="flex:1;font-size:13px;">
        </div>
        <div style="display:flex;gap:10px;">
            <a href="paren.php?child=<?= $c['id'] ?>" class="btn btn-add" style="flex:1;text-align:center;">Vaata andmeid</a>
            <form method="post" onsubmit="return confirm('Kustutada <?= htmlspecialchars(addslashes($c['name'])) ?> ja kõik tema kanded/raamatud jäädavalt?');">
                <?= csrf_field() ?>
                <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn-delete-full">Kustuta laps</button>
            </form>
        </div>

        <form method="post" class="goal-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="goal">
            <input type="hidden" name="child_id" value="<?= $c['id'] ?>">
            <p class="goal-form-head">Eesmärgid ja ekraanitasu</p>
            <div class="goal-form-row">
                <div>
                    <label for="dg<?= $c['id'] ?>">Eesmärk päevas (min)</label>
                    <input type="number" id="dg<?= $c['id'] ?>" name="daily_goal_min" min="0" inputmode="numeric" placeholder="nt. 30" value="<?= (int) ($c['daily_goal_min'] ?? 0) ?: '' ?>">
                </div>
                <div>
                    <label for="wg<?= $c['id'] ?>">Eesmärk nädalas (min)</label>
                    <input type="number" id="wg<?= $c['id'] ?>" name="weekly_goal_min" min="0" inputmode="numeric" placeholder="nt. 210" value="<?= (int) ($c['weekly_goal_min'] ?? 0) ?: '' ?>">
                </div>
            </div>
            <div class="goal-form-row">
                <div>
                    <label for="rc<?= $c['id'] ?>">Ekraaniaja tasu ülempiir (min/p)</label>
                    <input type="number" id="rc<?= $c['id'] ?>" name="screen_reward_cap_min" min="0" inputmode="numeric" placeholder="nt. 60" value="<?= (int) ($c['screen_reward_cap_min'] ?? 0) ?: '' ?>">
                </div>
                <div>
                    <label for="rr<?= $c['id'] ?>">Lugemise ja ekraani suhe</label>
                    <input type="number" id="rr<?= $c['id'] ?>" name="reading_ratio" min="0.1" max="9.99" step="0.1" inputmode="decimal" placeholder="1" value="<?= isset($c['reading_ratio']) && $c['reading_ratio'] !== null ? (float) $c['reading_ratio'] : '' ?>" <?= $canCustomRatio ? '' : 'disabled' ?>>
                    <?php if (!$canCustomRatio): ?><span class="gate-inline">🌟 Pere+</span><?php endif; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-add full-width">Salvesta</button>
            <p class="goal-form-note">Ekraaniaja tasu: kui laps loeb rohkem kui vaatab, näeb ta „teenitud" ekraaniaega (kuni ülempiirini). Tühi = väljas.<br>Suhe: mitu minutit lugemist tasakaalustab 1 ekraaniminuti. 1 = võrdne, 2 = ekraan „kallim", 0.5 = „soodsam". Tühi = üldreegel (<?= (float) READING_RATIO ?>).</p>
        </form>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($children) && !$canMultiChild): ?>
        <?php render_upgrade_gate('Rohkem kui 1 laps') ?>
    <?php else: ?>
    <div class="card">
        <h2>Lisa uus laps</h2>
        <form method="post">
            <?= csrf_field() ?>
            <label for="name">Nimi</label>
            <input type="text" id="name" name="name" placeholder="nt. Mari" required>
            <button type="submit" class="btn btn-add full-width">Lisa laps</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>Vanemate ligipääs</h2>
        <p class="goal-form-note" style="margin:0 0 14px;">Igal vanemal on oma e-post ja parool, aga kõik näevad ja saavad muuta sama pere andmeid.</p>

        <div class="fl-row">
            <div class="fl-main">
                <div class="fl-email"><?= htmlspecialchars($family['email'] ?? '') ?></div>
                <div class="fl-tag">Peakonto<?= current_login_email() === ($family['email'] ?? '') ? ' · sina' : '' ?></div>
            </div>
        </div>
        <?php foreach ($logins as $l): ?>
        <div class="fl-row">
            <div class="fl-main">
                <div class="fl-email"><?= htmlspecialchars($l['email']) ?></div>
                <div class="fl-tag"><?= $l['name'] ? htmlspecialchars($l['name']) . ' · ' : '' ?>teine vanem<?= current_login_email() === $l['email'] ? ' · sina' : '' ?></div>
            </div>
            <?php if (!$isDemo): ?>
            <form method="post" onsubmit="return confirm('Eemaldada selle vanema ligipääs?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="del_login">
                <input type="hidden" name="login_id" value="<?= (int) $l['id'] ?>">
                <button type="submit" class="btn-reject">Eemalda</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$canSecondParent): ?>
        <?php render_upgrade_gate('Teise vanema lisamine') ?>
    <?php else: ?>
    <div class="card">
        <h2>Lisa teine vanem</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_login">
            <label for="fl_email">E-post</label>
            <input type="email" id="fl_email" name="email" required <?= $isDemo ? 'disabled' : '' ?>>
            <label for="fl_name">Nimi (valikuline)</label>
            <input type="text" id="fl_name" name="name" <?= $isDemo ? 'disabled' : '' ?>>
            <label for="fl_pw">Parool</label>
            <input type="password" id="fl_pw" name="password" required <?= $isDemo ? 'disabled' : '' ?>>
            <label for="fl_pw2">Parool uuesti</label>
            <input type="password" id="fl_pw2" name="password_confirm" required <?= $isDemo ? 'disabled' : '' ?>>
            <button type="submit" class="btn btn-add full-width" <?= $isDemo ? 'disabled' : '' ?>>Lisa vanem</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>Muuda oma parooli</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_pw">
            <label for="cp_cur">Praegune parool</label>
            <input type="password" id="cp_cur" name="current_password" required <?= $isDemo ? 'disabled' : '' ?>>
            <label for="cp_new">Uus parool</label>
            <input type="password" id="cp_new" name="new_password" required <?= $isDemo ? 'disabled' : '' ?>>
            <label for="cp_new2">Uus parool uuesti</label>
            <input type="password" id="cp_new2" name="new_password_confirm" required <?= $isDemo ? 'disabled' : '' ?>>
            <button type="submit" class="btn btn-add full-width" <?= $isDemo ? 'disabled' : '' ?>>Salvesta parool</button>
        </form>
    </div>
</div>
</body>
</html>
