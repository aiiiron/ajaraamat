<?php
// Run this ONCE in your browser to add the "Lugemata" (unread/backlog)
// book status to an existing database. Safe to reload.
//
// This version is deliberately paranoid: it writes output immediately
// (instead of only at the end) and registers a shutdown handler, so if
// something crashes in a way normal try/catch cannot reach — a fatal
// error, a hosting timeout, anything — you still see SOMETHING instead
// of a blank page.
//
// IMPORTANT: delete this file from your server once the migration is done.

error_reporting(E_ALL);
ini_set('display_errors', '1');

$finished = false;

register_shutdown_function(function () use (&$finished) {
    if ($finished) return;
    $err = error_get_last();
    echo '<hr><p style="font-family:monospace;white-space:pre-wrap;color:#b91c1c;">';
    if ($err) {
        echo "SCRIPT DID NOT FINISH NORMALLY.\n\n";
        echo 'Type: ' . $err['type'] . "\n";
        echo 'Message: ' . htmlspecialchars($err['message']) . "\n";
        echo 'File: ' . htmlspecialchars($err['file']) . "\n";
        echo 'Line: ' . $err['line'] . "\n";
    } else {
        echo "SCRIPT DID NOT FINISH NORMALLY, and PHP reported no error at all.\n";
        echo "This usually means the hosting environment killed the process directly\n";
        echo "(e.g. a resource/security limit) rather than PHP itself failing.\n";
        echo "Check your hosting control panel's PHP/error log for this exact timestamp.\n";
    }
    echo '</p>';
});

echo '<!DOCTYPE html><html lang="et"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>Lugemata staatus — Ajaraamat</title></head><body style="font-family:-apple-system,sans-serif;max-width:600px;margin:40px auto;padding:0 20px;">';
echo '<h1>📥 "Lugemata" staatus</h1>';
if (ob_get_level() > 0) { ob_flush(); }
flush();

echo '<p>1. Laen config.php ja db.php...</p>';
if (ob_get_level() > 0) { ob_flush(); }
flush();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

echo '<p>2. Ühendun andmebaasiga...</p>';
if (ob_get_level() > 0) { ob_flush(); }
flush();

$pdo = get_db();

echo '<p>3. Kontrollin praegust staatust...</p>';
if (ob_get_level() > 0) { ob_flush(); }
flush();

$columnInfo = $pdo->query("SHOW COLUMNS FROM books LIKE 'status'")->fetch();
$alreadyDone = $columnInfo && strpos($columnInfo['Type'], 'lugemata') !== false;

if ($alreadyDone) {
    echo '<p>✅ "lugemata" on juba olemas — midagi rohkem pole vaja teha.</p>';
} else {
    echo '<p>4. Muudan books.status veergu...</p>';
    if (ob_get_level() > 0) { ob_flush(); }
    flush();

    $pdo->exec("ALTER TABLE books MODIFY status ENUM('lugemata','loeb','loetud') NOT NULL DEFAULT 'lugemata'");

    echo '<p>✅ Valmis! Lisasin "lugemata" staatuse. Olemasolevad raamatud ei muutunud.</p>';
}

echo '<p style="color:#dc2626;"><strong>Kustuta see fail (migrate_add_unread_status.php) FTP kaudu ära.</strong></p>';
echo '<p><a href="books.php">Raamatute juurde →</a></p>';
echo '</body></html>';

$finished = true;
