<?php
// One-tap into a demo child's own view. child.php is fully token-based (no
// session/login involved at all), so unlike demo_parent.php this can never
// interfere with anyone else's login — it's just a redirect.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pdo = get_db();
$token = (string) $pdo->query("SELECT c.public_token
    FROM children c JOIN families f ON f.id = c.family_id
    WHERE f.is_demo = 1 AND f.status = 'approved'
    ORDER BY c.id ASC LIMIT 1")->fetchColumn();

if ($token !== '') {
    header('Location: child.php?token=' . urlencode($token));
    exit;
}

header('Location: index.php');
exit;
