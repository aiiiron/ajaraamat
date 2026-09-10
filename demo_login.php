<?php
// One-tap login to the shared demo family. No password — the account holds
// only throwaway data and is re-seeded nightly.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
configure_session();

$pdo = get_db();
$id = (int) $pdo->query("SELECT id FROM families WHERE is_demo = 1 AND status = 'approved' ORDER BY id ASC LIMIT 1")->fetchColumn();

if ($id > 0) {
    session_regenerate_id(true);
    // Clear any leftover admin-panel flag from this browser (e.g. the owner
    // trying the demo from the same session they use for admin.php) so the
    // demo account never shows the Admin link.
    unset($_SESSION['is_admin']);
    $_SESSION['family_id']   = $id;
    $_SESSION['login_kind']  = 'demo';
    $_SESSION['login_id']    = $id;
    $_SESSION['login_email'] = 'demo';
    $_SESSION['is_demo']     = 1;
    header('Location: paren.php');
    exit;
}

header('Location: login.php');
exit;
