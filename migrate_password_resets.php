<?php
// Run this ONCE in your browser to add the `password_resets` table to an
// existing database (schema.sql only runs on a fresh install). Safe to
// reload — it uses CREATE TABLE IF NOT EXISTS.
//
// You must be logged in as the site owner (admin_login.php) to run it.
//
// IMPORTANT: delete this file from your server once the migration is done.

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/admin_auth.php'; // redirects to admin_login.php if not the owner

$finished = false;

register_shutdown_function(function () use (&$finished) {
    if ($finished) return;
    $err = error_get_last();
    echo '<hr><p style="font-family:monospace;white-space:pre-wrap;color:#b91c1c;">';
    if ($err) {
        echo "SCRIPT DID NOT FINISH NORMALLY.\n\n";
        echo 'Message: ' . htmlspecialchars($err['message']) . "\n";
        echo 'File: ' . htmlspecialchars($err['file']) . ':' . $err['line'] . "\n";
    } else {
        echo "SCRIPT DID NOT FINISH NORMALLY, and PHP reported no error.\n";
        echo "Check your hosting control panel's PHP error log for this timestamp.\n";
    }
    echo '</p>';
});

echo '<!DOCTYPE html><html lang="et"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>password_resets — Ajaraamat</title></head><body style="font-family:-apple-system,sans-serif;max-width:600px;margin:40px auto;padding:0 20px;">';
echo '<h1>🔑 password_resets tabel</h1>';
if (ob_get_level() > 0) { ob_flush(); }
flush();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pdo = get_db();

echo '<p>1. Kontrollin, kas tabel on juba olemas...</p>';
if (ob_get_level() > 0) { ob_flush(); }
flush();

$exists = $pdo->query("SHOW TABLES LIKE 'password_resets'")->fetch();

if ($exists) {
    echo '<p>✅ Tabel <code>password_resets</code> on juba olemas — midagi rohkem pole vaja teha.</p>';
} else {
    echo '<p>2. Loon tabeli...</p>';
    if (ob_get_level() > 0) { ob_flush(); }
    flush();

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            family_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            token CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_password_resets_family (family_id),
            KEY idx_password_resets_token_hash (token_hash),
            FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
        )"
    );

    echo '<p>✅ Valmis! Tabel <code>password_resets</code> on loodud.</p>';
}

echo '<p style="color:#dc2626;"><strong>Kustuta see fail (migrate_password_resets.php) FTP kaudu ära.</strong></p>';
echo '<p><a href="admin.php">Admin paneeli →</a></p>';
echo '</body></html>';

$finished = true;
