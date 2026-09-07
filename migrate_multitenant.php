<?php
// Run this ONCE in your browser to upgrade an existing single-family
// database to the multi-tenant version. Safe to reload — every step
// checks whether it already ran before doing anything.
//
// This version shows the exact error if a step fails, instead of a
// blank HTTP 500 page.
//
// IMPORTANT: delete this file from your server once the migration is done.

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$pdo = get_db();
$log = [];      // successful steps
$warnings = []; // non-fatal problems (e.g. couldn't add FK, migration still works without it)
$fatalError = '';

function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool) $stmt->fetch();
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($column));
    return (bool) $stmt->fetch();
}

/** Runs one DDL/DML statement, logging success or a warning — never lets it crash the page. */
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

try {
    // Step 1: create families/children tables if missing (InnoDB explicitly,
    // since foreign keys below need it).
    if (!table_exists($pdo, 'families')) {
        try_step($pdo, "CREATE TABLE families (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB", 'Lõin families tabeli.', $log, $warnings);
    } else {
        $log[] = 'families tabel on juba olemas.';
    }

    if (!table_exists($pdo, 'children')) {
        try_step($pdo, "CREATE TABLE children (
            id INT AUTO_INCREMENT PRIMARY KEY,
            family_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            public_token VARCHAR(40) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB", 'Lõin children tabeli.', $log, $warnings);
    } else {
        $log[] = 'children tabel on juba olemas.';
    }

    // Step 2: make sure entries/books are InnoDB (needed for foreign keys later).
    // MyISAM tables (common on older phpMyAdmin defaults) silently ignore FK
    // clauses, which otherwise causes confusing failures further down.
    foreach (['entries', 'books'] as $t) {
        if (table_exists($pdo, $t)) {
            $engineStmt = $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($t));
            $engine = $engineStmt->fetchColumn();
            if ($engine && strtoupper($engine) !== 'INNODB') {
                try_step($pdo, "ALTER TABLE `$t` ENGINE=InnoDB", "Muutsin $t mootoriks InnoDB (oli $engine).", $log, $warnings);
            }
        }
    }

    // Step 3: add a nullable child_id column to entries/books if missing.
    if (table_exists($pdo, 'entries') && !column_exists($pdo, 'entries', 'child_id')) {
        try_step($pdo, "ALTER TABLE entries ADD COLUMN child_id INT NULL AFTER id", 'Lisasin entries.child_id veeru.', $log, $warnings);
    }
    if (table_exists($pdo, 'books') && !column_exists($pdo, 'books', 'child_id')) {
        try_step($pdo, "ALTER TABLE books ADD COLUMN child_id INT NULL AFTER id", 'Lisasin books.child_id veeru.', $log, $warnings);
    }
} catch (Exception $e) {
    $fatalError = $e->getMessage();
}

$familyCount = 0;
$needsBackfill = false;
if (!$fatalError) {
    try {
        $familyCount = (int) $pdo->query("SELECT COUNT(*) FROM families")->fetchColumn();
        if (table_exists($pdo, 'entries') && column_exists($pdo, 'entries', 'child_id')) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM entries WHERE child_id IS NULL");
            if ((int) $stmt->fetchColumn() > 0) $needsBackfill = true;
        }
    } catch (Exception $e) {
        $fatalError = $e->getMessage();
    }
}

$error = '';
$done = false;

if (!$fatalError && $_SERVER['REQUEST_METHOD'] === 'POST' && $familyCount === 0) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $childName = trim($_POST['child_name'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Sisesta kehtiv e-posti aadress.';
    } elseif (strlen($password) < 6) {
        $error = 'Parool peab olema vähemalt 6 tähemärki.';
    } elseif ($childName === '') {
        $error = 'Sisesta lapse nimi.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO families (email, password_hash, status) VALUES (:email, :hash, 'approved')");
            $stmt->execute([':email' => $email, ':hash' => password_hash($password, PASSWORD_DEFAULT)]);
            $familyId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO children (family_id, name, public_token) VALUES (:fid, :name, :token)");
            $stmt->execute([':fid' => $familyId, ':name' => $childName, ':token' => generate_token()]);
            $childId = (int) $pdo->lastInsertId();
            $log[] = "Lõin sinu konto ja lapse \"$childName\" (id $childId).";

            if (table_exists($pdo, 'entries')) {
                $n = $pdo->prepare("UPDATE entries SET child_id = :cid WHERE child_id IS NULL");
                $n->execute([':cid' => $childId]);
                $log[] = 'Sidusin ' . $n->rowCount() . ' kannet lapsega.';
            }
            if (table_exists($pdo, 'books')) {
                $n = $pdo->prepare("UPDATE books SET child_id = :cid WHERE child_id IS NULL");
                $n->execute([':cid' => $childId]);
                $log[] = 'Sidusin ' . $n->rowCount() . ' raamatut lapsega.';
            }

            // Tighten the column and add the FK. These are best-effort: if they
            // fail (e.g. hosting restricts ALTER privileges), the app still
            // works fine — child_id just won't have an enforced foreign key.
            if (table_exists($pdo, 'entries')) {
                try_step($pdo, "ALTER TABLE entries MODIFY child_id INT NOT NULL", 'Muutsin entries.child_id kohustuslikuks.', $log, $warnings);
                $fkCheck = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entries' AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchColumn();
                if ($fkCheck === 0) {
                    try_step($pdo, "ALTER TABLE entries ADD CONSTRAINT fk_entries_child FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE", 'Lisasin entries -> children seose.', $log, $warnings);
                }
            }
            if (table_exists($pdo, 'books')) {
                try_step($pdo, "ALTER TABLE books MODIFY child_id INT NOT NULL", 'Muutsin books.child_id kohustuslikuks.', $log, $warnings);
                $fkCheck = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'books' AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchColumn();
                if ($fkCheck === 0) {
                    try_step($pdo, "ALTER TABLE books ADD CONSTRAINT fk_books_child FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE", 'Lisasin books -> children seose.', $log, $warnings);
                }
            }

            $done = true;
        } catch (Exception $e) {
            $error = 'Midagi läks valesti: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Migratsioon — Ajaraamat</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body class="centered">
<div class="card login-card" style="max-width:520px;">
    <h1>🔧 Andmebaasi uuendus</h1>

    <?php if ($fatalError): ?>
        <p class="error">Tõsine viga: <?= htmlspecialchars($fatalError) ?></p>
        <p style="font-size:13px;color:var(--text-muted);margin-top:8px;">
            Kontrolli, et <code>config.php</code>-s on õiged andmebaasi andmed
            ja et sinu andmebaasi kasutajal on õigus tabeleid luua/muuta (CREATE, ALTER).
        </p>
    <?php else: ?>

        <?php if (!empty($log)): ?>
            <p style="font-weight:600;font-size:13px;margin-top:12px;">Tehtud sammud:</p>
            <ul style="text-align:left;font-size:13px;color:var(--text-muted);margin:8px 0;">
                <?php foreach ($log as $l): ?><li><?= htmlspecialchars($l) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($warnings)): ?>
            <p style="font-weight:600;font-size:13px;margin-top:12px;color:#b45309;">Hoiatused (ei takistanud jätkamist):</p>
            <ul style="text-align:left;font-size:13px;color:#b45309;margin:8px 0;">
                <?php foreach ($warnings as $w): ?><li><?= htmlspecialchars($w) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($done): ?>
            <p style="margin:16px 0;">Valmis! Sinu senine andmebaas on nüüd seotud sinu uue kontoga. Saad kohe sisse logida.</p>
            <p style="color:#dc2626;font-size:13px;margin-bottom:16px;"><strong>Kustuta see fail (migrate_multitenant.php) FTP kaudu ära</strong> — seda ei ole enam vaja ja see ei tohiks serverisse jääda.</p>
            <a href="login.php" class="link-muted login-home-link">Logi sisse →</a>
        <?php elseif ($familyCount > 0): ?>
            <p style="margin:16px 0;">Migratsioon on juba tehtud (families tabelis on kirjeid). Kui soovid uue pere lisada, kasuta tavalist registreerimislehte.</p>
            <a href="login.php" class="link-muted login-home-link">Logi sisse →</a>
        <?php elseif ($needsBackfill): ?>
            <p style="color:var(--text-muted);font-size:14px;margin-bottom:16px;">
                Tabelid on uuendatud. Loo nüüd oma konto — sinu senised kanded ja raamatud
                seotakse automaatselt selle konto esimese lapsega.
            </p>
            <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            <form method="post">
                <label for="email">Sinu e-post</label>
                <input type="email" id="email" name="email" required autofocus>

                <label for="password">Parool</label>
                <input type="password" id="password" name="password" required minlength="6">

                <label for="child_name">Lapse nimi (senised andmed lähevad temale)</label>
                <input type="text" id="child_name" name="child_name" required>

                <button type="submit">Loo konto ja seo andmed</button>
            </form>
        <?php else: ?>
            <p style="margin:16px 0;">Tabelid on värskendatud ja backfill'i pole vaja (pole olemasolevaid kandeid). Mine registreerimislehele, et luua oma konto tavapärasel viisil.</p>
            <a href="register.php" class="link-muted login-home-link">Registreeru →</a>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
