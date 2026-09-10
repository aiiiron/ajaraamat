<?php
// Re-seed the public demo family with fresh throwaway data.
// Idempotent: wipes the demo family's content and rebuilds it.
//
// Run it from a Hostinger cron job once a night, e.g.:
//   0 4 * * *  curl -s "https://ajaraamat.ee/seed_demo.php?key=YOURKEY" > /dev/null
// Access is allowed with ?key=<DEMO_SEED_KEY from config.php> or as the site owner.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
configure_session();

$key = defined('DEMO_SEED_KEY') ? (string) DEMO_SEED_KEY : '';
$allowed = ($key !== '' && hash_equals($key, (string) ($_GET['key'] ?? ''))) || is_admin();
if (!$allowed) {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = get_db();
$pdo->beginTransaction();

try {

// ---- 1. demo family -------------------------------------------------------
$familyId = (int) $pdo->query("SELECT id FROM families WHERE is_demo = 1 ORDER BY id ASC LIMIT 1")->fetchColumn();
if ($familyId === 0) {
    $ins = $pdo->prepare("INSERT INTO families (email, password_hash, status, is_demo) VALUES (:e, :h, 'approved', 1)");
    $ins->execute([':e' => 'demo@ajaraamat.local', ':h' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
    $familyId = (int) $pdo->lastInsertId();
} else {
    $pdo->prepare("UPDATE families SET status = 'approved', is_demo = 1 WHERE id = :id")->execute([':id' => $familyId]);
}

// ---- 2. wipe old content ------------------------------------------------------
$pdo->prepare("DELETE FROM children WHERE family_id = :fid")->execute([':fid' => $familyId]);        // cascades entries/books/challenges/milestones/pending
$pdo->prepare("DELETE FROM family_logins WHERE family_id = :fid")->execute([':fid' => $familyId]);

// second parent login on the demo family, so the "Vanemate ligipääs" list shows two
$pdo->prepare("INSERT INTO family_logins (family_id, email, name, password_hash) VALUES (:fid, :e, 'Peeter', :h)")
    ->execute([':fid' => $familyId, ':e' => 'teine.vanem+' . $familyId . '@ajaraamat.local', ':h' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);

// ---- 3. helpers ------------------------------------------------------------
$insBook = $pdo->prepare("INSERT INTO books (child_id, title, author, status, started_date, finished_date, total_pages, current_page)
    VALUES (:cid, :t, :a, :s, :sd, :fd, :tp, :cp)");
$insEntry = $pdo->prepare("INSERT INTO entries (child_id, entry_date, raamat, book_id, raamat_comment, kind, ekraan, ekraan_comment)
    VALUES (:cid, :d, :r, :bid, :rc, :k, :e, :ec)");
$insChal = $pdo->prepare("INSERT INTO challenges (child_id, title, goal_type, goal_value, start_date, end_date)
    VALUES (:cid, :t, :gt, :gv, :sd, :ed)");
$insPending = $pdo->prepare("INSERT INTO pending_entries (child_id, entry_date, type, minutes, book_id, note, current_page, source)
    VALUES (:cid, :d, :t, :m, :bid, :note, :cp, :src)");

$rNotes = ['hea peatükk!', 'väga põnev', 'naljakas koht', 'lugesin voodis', 'ise lugesin', ''];
$eNotes = ['Youtube', 'Multikas', 'Minecraft', 'koolitöö video', 'joonisfilm'];
$d = fn(int $daysAgo) => date('Y-m-d', strtotime("-$daysAgo days"));

// ---- 4. kids ------------------------------------------------------------------
$kids = [
    [
        'name' => 'Liis', 'daily' => 30, 'weekly' => 180, 'cap' => 45, 'ratio' => null,
        'books' => [
            ['Karlsson katuselt', 'Astrid Lindgren', 'loeb',     18, null, 431, 205],
            ['Nukitsamees',       'Oskar Luts',      'loetud',    40, 22,   180, 180],
            ['Sipsik',            'Eno Raud',        'lugemata',  null, null, 96, null],
        ],
        'challenge' => ['Sügisene lugemismaraton', 'books', 8, 10, 50],
    ],
    [
        'name' => 'Oskar', 'daily' => 20, 'weekly' => 120, 'cap' => 30, 'ratio' => 1.5,
        'books' => [
            ['Pipi Pikksukk',              'Astrid Lindgren', 'loeb',   25, null, 220, 138],
            ['Aabits',                     null,              'loetud', 60, 35,   60,  60],
            ['Lotte reis lõunamaale',      'Janno Põldma',    'loeb',   9,  null, 120, 44],
        ],
        'challenge' => ['Loe 500 minutit', 'minutes', 500, 14, 30],
    ],
];

foreach ($kids as $k) {
    $cid = create_child($familyId, $k['name']);
    set_child_goal($cid, $k['daily'], $k['weekly'], $k['cap'], $k['ratio']);

    $bookIds = [];
    foreach ($k['books'] as $b) {
        [$title, $author, $status, $startAgo, $finAgo, $tp, $cp] = $b;
        $insBook->execute([
            ':cid' => $cid, ':t' => $title, ':a' => $author, ':s' => $status,
            ':sd' => $startAgo !== null ? $d($startAgo) : null,
            ':fd' => $finAgo !== null ? $d($finAgo) : null,
            ':tp' => $tp, ':cp' => $cp,
        ]);
        $bookIds[] = ['id' => (int) $pdo->lastInsertId(), 'status' => $status];
    }
    $readingBooks = array_values(array_filter($bookIds, fn($x) => $x['status'] !== 'lugemata'));

    // ~35 days of entries
    for ($i = 35; $i >= 0; $i--) {
        $roll = ($i * 7 + strlen($k['name'])) % 10;
        if ($roll === 3 || $roll === 7) continue; // skip a couple of days

        // reading
        $rmin = 10 + (($i * 5 + 3) % 36);
        if ($roll !== 5) {
            $isPoem = ($i % 11) === 0;
            $book = $readingBooks[($i + strlen($k['name'])) % max(1, count($readingBooks))] ?? null;
            $insEntry->execute([
                ':cid' => $cid, ':d' => $d($i),
                ':r' => $isPoem ? 8 + ($i % 12) : $rmin,
                ':bid' => $isPoem ? null : ($book['id'] ?? null),
                ':rc' => $isPoem ? 'Sügisluuletus' : $rNotes[($i + 1) % count($rNotes)],
                ':k' => $isPoem ? 'luuletus' : null,
                ':e' => 0, ':ec' => null,
            ]);
        }
        // screen
        if ($roll !== 1 && $roll !== 8) {
            $emin = 15 + (($i * 9 + 5) % 46);
            $insEntry->execute([
                ':cid' => $cid, ':d' => $d($i),
                ':r' => 0, ':bid' => null, ':rc' => null, ':k' => null,
                ':e' => $emin, ':ec' => $eNotes[($i + strlen($k['name'])) % count($eNotes)],
            ]);
        }
    }

    // challenge
    [$ct, $cgt, $cgv, $csd, $ced] = $k['challenge'];
    $insChal->execute([':cid' => $cid, ':t' => $ct, ':gt' => $cgt, ':gv' => $cgv, ':sd' => $d($csd), ':ed' => date('Y-m-d', strtotime("+$ced days"))]);

    // a couple of pending self-logs for the first kid only
    if ($k['name'] === 'Liis' && !empty($readingBooks)) {
        $insPending->execute([':cid' => $cid, ':d' => $d(0), ':t' => 'raamat', ':m' => 25, ':bid' => $readingBooks[0]['id'], ':note' => 'Lisatud taimeriga', ':cp' => 230, ':src' => 'taimer']);
        $insPending->execute([':cid' => $cid, ':d' => $d(0), ':t' => 'ekraan', ':m' => 30, ':bid' => null, ':note' => 'Multikas', ':cp' => null, ':src' => null]);
    }
}

$pdo->commit();

header('Content-Type: text/plain; charset=utf-8');
echo "Demo family #$familyId re-seeded OK — " . date('Y-m-d H:i:s') . "\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Demo seed failed, nothing changed: " . $e->getMessage() . "\n";
}
