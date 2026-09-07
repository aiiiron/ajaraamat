<?php
// Run this ONCE in your browser (e.g. https://yourdomain/loevaata/migrate_link_books.php)
// to add book linking to an existing database. Safe to reload.
//
// IMPORTANT: delete this file from your server once the migration is done.

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pdo = get_db();
$log = [];
$warnings = [];
$fatalError = '';

function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool) $stmt->fetch();
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($column));
    return (bool) $stmt->fetch();
}

function try_step(PDO $pdo, string $sql, string $successMsg, array &$log, array &$warnings): bool {
    try {
        $pdo->exec($sql);
        $log[] = $successMsg;
        return true;
    } catch (Exception $e) {
        $warnings[] = $successMsg . ' — EBAÕNNESTUS: ' . $e->getMessage();
        return false;
    }
}

$linked = 0;

try {
    if (!table_exists($pdo, 'entries') || !table_exists($pdo, 'books')) {
        $fatalError = 'entries või books tabelit ei leitud. Käivita esmalt migrate_multitenant.php.';
    } else {
        if (!column_exists($pdo, 'entries', 'book_id')) {
            try_step($pdo, "ALTER TABLE entries ADD COLUMN book_id INT NULL AFTER child_id", 'Lisasin entries.book_id veeru.', $log, $warnings);
        } else {
            $log[] = 'entries.book_id on juba olemas.';
        }

        $fkCheck = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND COLUMN_NAME = 'book_id' AND REFERENCED_TABLE_NAME = 'books'")->fetchColumn();
        if ($fkCheck === 0) {
            try_step($pdo, "ALTER TABLE entries ADD CONSTRAINT fk_entries_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE SET NULL", 'Lisasin entries -> books seose.', $log, $warnings);
        } else {
            $log[] = 'entries -> books seos on juba olemas.';
        }

        // Best-effort auto-link: for each child, match existing raamat_comment
        // text to an existing book title (case-insensitive, exact match only —
        // "Karlsson katuselt" and "karlsson katuselt" both match, but a typo
        // or partial title won't, to avoid linking the wrong book).
        $stmt = $pdo->query("SELECT DISTINCT child_id, raamat_comment FROM entries
            WHERE book_id IS NULL AND raamat > 0 AND raamat_comment IS NOT NULL AND raamat_comment != ''");
        $candidates = $stmt->fetchAll();

        foreach ($candidates as $c) {
            $matchStmt = $pdo->prepare("SELECT id FROM books WHERE child_id = :cid AND LOWER(title) = LOWER(:title)");
            $matchStmt->execute([':cid' => $c['child_id'], ':title' => $c['raamat_comment']]);
            $matches = $matchStmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($matches) === 1) {
                $upd = $pdo->prepare("UPDATE entries SET book_id = :bid WHERE child_id = :cid AND book_id IS NULL AND raamat_comment = :title AND raamat > 0");
                $upd->execute([':bid' => $matches[0], ':cid' => $c['child_id'], ':title' => $c['raamat_comment']]);
                $linked += $upd->rowCount();
            }
        }
        if ($linked > 0) {
            $log[] = "Sidusin automaatselt $linked kannet olemasoleva raamatuga (täpse pealkirja kokkulangevuse alusel).";
        }

        $remaining = (int) $pdo->query("SELECT COUNT(*) FROM entries WHERE book_id IS NULL AND raamat > 0 AND raamat_comment IS NOT NULL AND raamat_comment != ''")->fetchColumn();
        if ($remaining > 0) {
            $log[] = "$remaining kande raamatut ei õnnestunud automaatselt siduda (nimi ei kattunud täpselt ühegi olemasoleva raamatuga). Need jäävad tavatekstina nähtavaks, kuni muudad neid käsitsi \"Kõik kanded\" lehel.";
        }
    }
} catch (Exception $e) {
    $fatalError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Raamatute sidumine — Ajaraamat</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body class="centered">
<div class="card login-card" style="max-width:520px;">
    <h1>🔗 Raamatute sidumine</h1>

    <?php if ($fatalError): ?>
        <p class="error">Tõsine viga: <?= htmlspecialchars($fatalError) ?></p>
    <?php else: ?>
        <?php if (!empty($log)): ?>
            <ul style="text-align:left;font-size:13px;color:var(--text-muted);margin:12px 0;">
                <?php foreach ($log as $l): ?><li><?= htmlspecialchars($l) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!empty($warnings)): ?>
            <p style="font-weight:600;font-size:13px;margin-top:12px;color:#b45309;">Hoiatused:</p>
            <ul style="text-align:left;font-size:13px;color:#b45309;margin:8px 0;">
                <?php foreach ($warnings as $w): ?><li><?= htmlspecialchars($w) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p style="margin:16px 0;">Valmis! Uued kanded saab nüüd siduda otse raamatuga.</p>
        <p style="color:#dc2626;font-size:13px;margin-bottom:16px;"><strong>Kustuta see fail (migrate_link_books.php) FTP kaudu ära.</strong></p>
        <a href="paren.php" class="link-muted login-home-link">Tagasi töölauale →</a>
    <?php endif; ?>
</div>
</body>
</html>
